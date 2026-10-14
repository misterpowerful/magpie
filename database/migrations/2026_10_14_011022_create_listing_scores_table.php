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
        Schema::create('listing_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('rating');
            $table->json('reasons')->nullable();
            $table->json('flags')->nullable();
            $table->text('commentary')->nullable();
            $table->string('prefs_hash');
            $table->string('resume_hash')->nullable();
            $table->string('model');
            $table->timestamp('scored_at');
            $table->timestamps();

            $table->unique('listing_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_scores');
    }
};
