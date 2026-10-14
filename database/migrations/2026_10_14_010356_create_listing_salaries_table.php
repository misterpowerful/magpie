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
        Schema::create('listing_salary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->json('tiers')->nullable();
            $table->text('snippet')->nullable();
            $table->string('parse_confidence')->default('none');
            $table->string('status')->default('unknown');
            $table->boolean('tiered')->default(false);
            $table->timestamps();

            $table->unique('listing_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_salary');
    }
};
