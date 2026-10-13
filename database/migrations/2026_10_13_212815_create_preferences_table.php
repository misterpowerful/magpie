<?php

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
        Schema::create('preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('remote_floor')->default(150000);
            $table->unsignedInteger('onsite_floor')->default(180000);
            $table->unsignedInteger('hard_min')->default(140000);
            $table->json('onsite_states')->default(json_encode(['IL']));
            $table->json('include_titles')->nullable();
            $table->json('exclude_titles')->nullable();
            $table->json('include_stack')->nullable();
            $table->json('exclude_stack')->nullable();
            $table->text('resume_text')->nullable();
            $table->string('resume_hash')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preferences');
    }
};
