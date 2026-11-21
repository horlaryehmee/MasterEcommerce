<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email sending set up from the admin (Settings → Email): SMTP or the server's PHP mail.
 * mail_mailer null = use the server's .env MAIL_* settings. The SMTP password is stored encrypted.
 * Each column is added only if missing, so a half-finished run can simply be repeated.
 */
return new class extends Migration
{
    protected const COLUMNS = ['mail_mailer', 'mail_host', 'mail_port', 'mail_encryption', 'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name'];

    public function up(): void
    {
        $add = [
            'mail_mailer' => fn (Blueprint $t) => $t->string('mail_mailer', 20)->nullable(),
            'mail_host' => fn (Blueprint $t) => $t->string('mail_host', 190)->nullable(),
            'mail_port' => fn (Blueprint $t) => $t->unsignedSmallInteger('mail_port')->nullable(),
            'mail_encryption' => fn (Blueprint $t) => $t->string('mail_encryption', 10)->nullable(),
            'mail_username' => fn (Blueprint $t) => $t->string('mail_username', 190)->nullable(),
            'mail_password' => fn (Blueprint $t) => $t->text('mail_password')->nullable(),
            'mail_from_address' => fn (Blueprint $t) => $t->string('mail_from_address', 160)->nullable(),
            'mail_from_name' => fn (Blueprint $t) => $t->string('mail_from_name', 120)->nullable(),
        ];

        foreach ($add as $column => $definition) {
            if (! Schema::hasColumn('business_settings', $column)) {
                Schema::table('business_settings', $definition);
            }
        }
    }

    public function down(): void
    {
        $existing = array_values(array_filter(self::COLUMNS, fn ($c) => Schema::hasColumn('business_settings', $c)));
        if ($existing) {
            Schema::table('business_settings', fn (Blueprint $t) => $t->dropColumn($existing));
        }
    }
};
