<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Two-factor sign-in (authenticator app codes) for admin accounts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Both encrypted with APP_KEY (casts on the model).
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            // Null until the admin proves the app works by entering a code.
            $table->timestamp('two_factor_confirmed_at')->nullable();
            // Last accepted time step: the same code can't be used twice.
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']));
    }
};
