<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function smtp(array $overrides = []): array
    {
        return [
            'mail_mailer' => 'smtp', 'mail_host' => 'mail.shop.test', 'mail_port' => 465, 'mail_encryption' => 'ssl',
            'mail_username' => 'orders@shop.test', 'mail_password' => 's3cret-Pass', 'mail_from_address' => 'orders@shop.test', 'mail_from_name' => 'Shop',
            ...$overrides,
        ];
    }

    public function test_smtp_settings_are_saved_with_the_password_encrypted_and_never_sent_to_the_browser(): void
    {
        $this->actingAsAdmin();
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);

        $this->put('/admin/settings/email', $this->smtp())->assertRedirect('/admin/settings?tab=email')->assertSessionHasNoErrors();

        $raw = DB::table('business_settings')->value('mail_password');
        $this->assertNotSame('s3cret-Pass', $raw);
        $this->assertSame('s3cret-Pass', BusinessSetting::query()->first()->mail_password);

        $this->get('/admin/settings?tab=email')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('settings.mail_mailer', 'smtp')
                ->where('settings.has_mail_password', true)
                ->missing('settings.mail_password')
                ->missing('settings.update_token'))
            ->assertDontSee('s3cret-Pass')
            ->assertDontSee($raw);
    }

    public function test_blank_password_keeps_the_saved_one_and_it_can_be_removed(): void
    {
        $this->actingAsAdmin();
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        $this->put('/admin/settings/email', $this->smtp());

        $this->put('/admin/settings/email', $this->smtp(['mail_password' => '', 'mail_port' => 587, 'mail_encryption' => 'tls']));
        $s = BusinessSetting::query()->first();
        $this->assertSame('s3cret-Pass', $s->mail_password);
        $this->assertSame(587, $s->mail_port);

        $this->put('/admin/settings/email', $this->smtp(['mail_password' => '', 'remove_mail_password' => true]));
        $this->assertNull(BusinessSetting::query()->first()->mail_password);
    }

    public function test_smtp_needs_a_valid_server_name_and_port(): void
    {
        $this->actingAsAdmin();
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);

        $this->put('/admin/settings/email', $this->smtp(['mail_host' => '', 'mail_port' => '']))->assertSessionHasErrors(['mail_host', 'mail_port']);
        $this->put('/admin/settings/email', $this->smtp(['mail_host' => 'https://mail.shop.test/']))->assertSessionHasErrors('mail_host');
        $this->put('/admin/settings/email', $this->smtp(['mail_encryption' => 'starttls']))->assertSessionHasErrors('mail_encryption');
        // PHP mail needs no server details.
        $this->put('/admin/settings/email', ['mail_mailer' => 'sendmail'])->assertSessionHasNoErrors();
    }

    public function test_saved_settings_configure_the_mailer(): void
    {
        $s = BusinessSetting::current();

        $s->update($this->smtp());
        MailSettings::apply($s);
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('mail.shop.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('s3cret-Pass', config('mail.mailers.smtp.password'));
        $this->assertSame('orders@shop.test', config('mail.from.address'));
        $this->assertSame('Shop', config('mail.from.name'));

        $s->update(['mail_encryption' => 'tls', 'mail_port' => 587]);
        MailSettings::apply($s);
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertTrue(config('mail.mailers.smtp.auto_tls'));

        $s->update(['mail_encryption' => 'none', 'mail_port' => 25]);
        MailSettings::apply($s);
        $this->assertFalse(config('mail.mailers.smtp.auto_tls'));

        $s->update(['mail_mailer' => 'sendmail']);
        MailSettings::apply($s);
        $this->assertSame('sendmail', config('mail.default'));
        $this->assertMatchesRegularExpression('/sendmail.* -(bs|t)/', config('mail.mailers.sendmail.path'));
    }

    public function test_without_a_choice_the_server_settings_apply_and_the_business_email_replaces_the_placeholder_sender(): void
    {
        config(['mail.default' => 'array', 'mail.from.address' => 'hello@example.com']);
        $s = BusinessSetting::current();
        $s->update(['mail_mailer' => null, 'email' => 'info@shop.test', 'business_name' => 'Ada Shop']);

        MailSettings::apply($s);

        $this->assertSame('array', config('mail.default'));
        $this->assertSame('info@shop.test', config('mail.from.address'));
        $this->assertSame('Ada Shop', config('mail.from.name'));
    }

    public function test_template_placeholder_smtp_server_is_treated_as_not_set_up(): void
    {
        // Older installs: .env still has the template's MAIL_MAILER=smtp / mail.example.com.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mail.example.com', 'mail.from.address' => 'no-reply@example.com']);
        MailSettings::flush();
        $s = BusinessSetting::current();
        $s->update(['mail_mailer' => null, 'email' => 'info@shop.test']);

        MailSettings::apply($s);

        $this->assertSame('log', config('mail.default'));
        $this->assertSame('log', MailSettings::serverMailer());
        $this->assertSame('info@shop.test', config('mail.from.address'));

        // A real server in .env is used as is.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mail.realhost.ng']);
        MailSettings::flush();
        MailSettings::apply($s);
        $this->assertSame('smtp', config('mail.default'));
    }

    public function test_failed_test_email_explains_the_error(): void
    {
        $this->actingAsAdmin();
        $this->withSession(['auth.password_confirmed_at' => now()->timestamp]);
        // Nothing listens on port 1, so the connection is refused at once.
        $this->put('/admin/settings/email', $this->smtp(['mail_host' => '127.0.0.1', 'mail_port' => 1, 'mail_encryption' => 'tls']));

        $this->post('/admin/settings/email/test', ['to' => 'owner@shop.test'])
            ->assertRedirect('/admin/settings?tab=email')
            ->assertSessionHas('error', fn ($msg) => str_contains($msg, 'could not be sent') && str_contains($msg, 'server name and port'));
    }

    public function test_author_credit_is_shared_and_in_the_html(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<meta name="author" content="Bakhtech Solutions">', false)
            ->assertInertia(fn (Assert $p) => $p->where('author.name', 'Bakhtech Solutions')->where('author.url', 'https://bakhtech.com.ng'));

        $this->get('/admin/login')->assertInertia(fn (Assert $p) => $p->where('author.name', 'Bakhtech Solutions'));
    }
}
