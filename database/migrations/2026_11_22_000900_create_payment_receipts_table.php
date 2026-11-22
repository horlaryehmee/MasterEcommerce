<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank-transfer receipts uploaded by customers on their order page.
 * Files live on the PRIVATE disk (storage/app/private/receipts): they show
 * bank details, so they are only ever served to a signed-in admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('kind', 10); // image (stored as WebP) | pdf
            $table->unsignedInteger('size');
            // pending → approved (order marked paid) | rejected (with a note the customer sees)
            $table->string('status', 10)->default('pending');
            $table->string('note', 200)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
