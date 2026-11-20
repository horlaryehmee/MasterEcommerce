<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Update agent: this site's connection to the central update server (one row,
 * secret stored encrypted) and a local log of everything the agent did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('update_agent', function (Blueprint $table) {
            $table->id();
            $table->string('server_url');
            $table->uuid('installation_id');
            $table->uuid('client_key');
            $table->text('secret'); // encrypted with APP_KEY
            $table->string('signing_key_id', 32);
            $table->text('signing_public_key');
            $table->string('signing_fingerprint', 64)->nullable();
            $table->unsignedSmallInteger('check_interval')->default(15);
            $table->string('status', 20)->default('active'); // active | suspended (as reported by the server)
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('pending')->nullable(); // last instructions received
            $table->timestamps();
        });

        Schema::create('agent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10)->default('info');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('message', 1000);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_logs');
        Schema::dropIfExists('update_agent');
    }
};
