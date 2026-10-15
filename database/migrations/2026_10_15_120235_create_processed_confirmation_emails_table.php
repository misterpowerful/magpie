<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('processed_confirmation_emails', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('outcome');
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_confirmation_emails');
    }
};
