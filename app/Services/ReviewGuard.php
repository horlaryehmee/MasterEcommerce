<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Anti-spam for reviews, in layers (no captcha, nothing for real customers to do):
 *
 *  1. Honeypot: a hidden field people never see; bots fill it in.
 *  2. Timing: the form carries an encrypted timestamp. Sent in under 4 seconds
 *     = a bot. Tampered or missing = a bot.
 *  3. Rate limits per visitor (hashed IP): 3 reviews per 10 minutes, 10 a day,
 *     and one review per product per visitor or email.
 *  4. Content: links, spam phrases, copies of another review, shouting and
 *     gibberish are flagged. Links/spam phrases/duplicates go to Spam.
 *  5. Moderation: nothing is published until approved, unless the owner chose
 *     to publish verified buyers' clean reviews automatically.
 *
 * Bots caught by 1–2 get a normal "thank you" (so they learn nothing) and
 * nothing is saved.
 */
class ReviewGuard
{
    public const MIN_SECONDS = 4;

    public const MAX_AGE_HOURS = 12;

    protected const SPAM_PHRASES = [
        'viagra', 'cialis', 'casino', 'bitcoin', 'forex', 'loan offer', 'payday loan', 'seo service', 'backlink',
        'work from home', 'make money', 'earn $', 'click here', 'buy followers', 'escort', 'porn', 'xxx', 'telegram @',
        'whatsapp me on', 'investment opportunity', 'binary option', 'free money', 'giveaway winner',
    ];

    /** A token for the review form: when it was shown, encrypted so it can't be faked. */
    public static function formToken(): string
    {
        return Crypt::encryptString((string) time());
    }

    /** True when this submission is clearly a bot (honeypot filled, too fast, forged token). */
    public function isBot(Request $request): bool
    {
        if (filled($request->input('website'))) {
            return true;
        }
        try {
            $shown = (int) Crypt::decryptString((string) $request->input('form_token'));
        } catch (Throwable) {
            return true;
        }
        $age = time() - $shown;

        return $age < self::MIN_SECONDS || $age > self::MAX_AGE_HOURS * 3600;
    }

    /** Throws a validation error when the visitor is reviewing too often or reviewed this product already. */
    public function checkLimits(Request $request, Product $product, ?string $email): string
    {
        $ip = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));

        foreach ([['review-burst:'.$ip, 3, 600], ['review-day:'.$ip, 10, 86400]] as [$key, $max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages(['body' => 'You’ve sent several reviews in a short time. Please try again later.']);
            }
        }

        $already = Review::query()->where('product_id', $product->id)->where('status', '!=', 'rejected')
            ->where(fn ($q) => $q->where('ip_hash', $ip)->when($email, fn ($q) => $q->orWhere('email', $email)))
            ->where('created_at', '>', now()->subDays(90))
            ->exists();
        if ($already) {
            throw ValidationException::withMessages(['body' => 'You’ve already reviewed this product. Thank you!']);
        }

        RateLimiter::hit('review-burst:'.$ip, 600);
        RateLimiter::hit('review-day:'.$ip, 86400);

        return $ip;
    }

    /** Content checks: [status, flags]. */
    public function assess(string $title, string $body, string $name, bool $verified): array
    {
        $text = mb_strtolower($title.' '.$body.' '.$name);
        $flags = [];

        if (preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|ru|cn|xyz|top|info|biz|io|link|click|online|site|ng)\b|<a\s|\[url)~i', $text)) {
            $flags[] = 'contains a link';
        }
        foreach (self::SPAM_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                $flags[] = 'spam words';
                break;
            }
        }
        if (Review::query()->where('body_hash', self::bodyHash($body))->where('created_at', '>', now()->subDays(180))->exists()) {
            $flags[] = 'copy of another review';
        }
        $letters = preg_replace('/[^\p{L}]/u', '', $body);
        if (mb_strlen($letters) >= 12 && mb_strtoupper($letters) === $letters && mb_strtolower($letters) !== $letters) {
            $flags[] = 'all capitals';
        }
        if (preg_match('/(.)\1{6,}/u', $body) || mb_strlen($letters) < mb_strlen($body) * 0.4) {
            $flags[] = 'looks like gibberish';
        }
        if (preg_match('/(\+?\d[\d\s\-]{8,}\d)|[\w.+-]+@[\w-]+\.\w+/u', $body)) {
            $flags[] = 'contains contact details';
        }

        $spam = array_intersect($flags, ['contains a link', 'spam words', 'copy of another review']) !== [];
        $autoPublish = BusinessSetting::current()->reviews_auto_publish === 'verified' && $verified && $flags === [];

        return [$spam ? 'spam' : ($autoPublish ? 'approved' : 'pending'), $flags];
    }

    /** The order that shows this email bought the product (paid or delivered), if any. */
    public function verifiedOrder(Product $product, ?string $email): ?Order
    {
        if (! $email) {
            return null;
        }

        return Order::query()
            ->whereRaw('LOWER(customer_email) = ?', [mb_strtolower($email)])
            ->where(fn ($q) => $q->where('payment_status', 'paid')->orWhereIn('status', ['shipped', 'completed']))
            ->where('status', '!=', 'cancelled')
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->latest('id')
            ->first();
    }

    public static function bodyHash(string $body): string
    {
        return hash('sha256', preg_replace('/\s+/', ' ', mb_strtolower(trim($body))));
    }

    /** Plain text only: no tags, no control characters, tidy spaces and blank lines. */
    public static function clean(?string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", strip_tags((string) $text));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }
}
