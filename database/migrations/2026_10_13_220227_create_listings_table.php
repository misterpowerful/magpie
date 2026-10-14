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
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('canonical_url');
            $table->string('company')->nullable();
            $table->string('title');
            $table->string('location_text')->nullable();
            $table->string('remote_type')->nullable();
            $table->longText('raw_text')->nullable();
            $table->string('content_hash')->index();
            $table->string('state')->default('new');
            $table->string('status')->default('new');
            $table->string('status_reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'canonical_url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
