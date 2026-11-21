<?php

namespace App\Support;

use App\Models\BusinessSetting;
use Throwable;

/**
 * Applies the email settings chosen in Admin → Settings → Email to Laravel's mail config.
 *
 * Runs when the mailer is first used in a request (see AppServiceProvider), so pages that
 * send no email never touch it. With no choice saved, the server's .env MAIL_* settings apply.
 */
class MailSettings
{
    /** Options in the admin: value => label. 'server' is stored as null. */
    public const MAILERS = [
        'smtp' => 'SMTP',
        'sendmail' => 'PHP mail (the server’s built-in mail)',
        'log' => 'Off (only write emails to the log file)',
        'server' => 'Server configuration (.env file)',
    ];

    public const ENCRYPTIONS = ['tls' => 'TLS / STARTTLS (port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (port 25)'];

    /** Laravel's placeholder sender, replaced by the business email when nothing else is set. */
    protected const PLACEHOLDER_FROM = 'hello@example.com';

    /** @var array{mailer: string, host: string}|null */
    protected static ?array $server = null;

    public static function apply(?BusinessSetting $s = null): void
    {
        try {
            $s ??= BusinessSetting::current();
        } catch (Throwable) {
            return; // No database yet (setup wizard): keep the .env settings.
        }
        self::server(); // capture the .env settings before they are overridden

        match ($s->mail_mailer) {
            'smtp' => config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    // 465 = SSL from the start; otherwise plain SMTP upgraded with STARTTLS when offered.
                    'scheme' => $s->mail_encryption === 'ssl' ? 'smtps' : 'smtp',
                    'host' => $s->mail_host,
                    'port' => $s->mail_port ?: ($s->mail_encryption === 'ssl' ? 465 : 587),
                    'username' => $s->mail_username ?: null,
                    'password' => $s->mail_username ? $s->mail_password : null,
                    'auto_tls' => $s->mail_encryption !== 'none',
                    // Don't let a wrong host hold up checkout for long.
                    'timeout' => 15,
                    'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
                ],
            ]),
            'sendmail' => config([
                'mail.default' => 'sendmail',
                // The same program PHP's mail() uses (cPanel: Exim's sendmail).
                'mail.mailers.sendmail.path' => self::sendmailPath(),
            ]),
            'log' => config(['mail.default' => 'log']),
            // Nothing chosen in the admin: the server's .env, unless it still holds the template's placeholders.
            default => self::serverIsPlaceholder() ? config(['mail.default' => 'log']) : null,
        };

        $from = $s->mail_from_address
            ?: (self::isPlaceholderAddress(config('mail.from.address')) ? $s->email : null);
        if ($from) {
            config(['mail.from.address' => $from]);
        }
        config(['mail.from.name' => $s->mail_from_name ?: $s->business_name]);
    }

    /** What the admin sees: which mailer is in use and whether emails really leave the server. */
    public static function status(?BusinessSetting $s = null): array
    {
        self::apply($s);
        $mailer = (string) config('mail.default');

        return [
            'mailer' => $mailer,
            'source' => ($s ?? BusinessSetting::current())->mail_mailer ? 'admin' : 'server',
            'delivers' => ! in_array($mailer, ['log', 'array'], true),
            'from' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    /**
     * The server's own (.env) mailer as the admin should see it: SMTP still pointing at the
     * template's example server (older installs) counts as not set up, so no email is attempted
     * against a host that doesn't exist (each try could hold up checkout until it times out).
     */
    public static function serverMailer(): string
    {
        return self::serverIsPlaceholder() ? 'log' : self::server()['mailer'];
    }

    protected static function serverIsPlaceholder(): bool
    {
        ['mailer' => $mailer, 'host' => $host] = self::server();

        return $mailer === 'smtp' && ($host === '' || $host === 'example.com' || str_ends_with($host, '.example.com'));
    }

    /** The server's own settings, read from config (works with a cached config) before apply() changes them. */
    protected static function server(): array
    {
        return self::$server ??= [
            'mailer' => (string) (config('mail.default') ?: 'log'),
            'host' => strtolower((string) config('mail.mailers.smtp.host')),
        ];
    }

    /** For tests: forget the captured server settings. */
    public static function flush(): void
    {
        self::$server = null;
    }

    protected static function isPlaceholderAddress(mixed $address): bool
    {
        $address = strtolower((string) $address);

        return $address === '' || $address === self::PLACEHOLDER_FROM || str_ends_with($address, '@example.com');
    }

    /** PHP's sendmail_path, if it is a sendmail Symfony can drive; otherwise the standard one. */
    public static function sendmailPath(): string
    {
        $path = trim((string) ini_get('sendmail_path'));

        return $path !== '' && (str_contains($path, ' -t') || str_contains($path, ' -bs')) ? $path : '/usr/sbin/sendmail -bs -i';
    }
}
