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
        Schema::table('llm_calls', function (Blueprint $table) {
            // Null for calls made outside a pipeline run (manual site
            // runs, ad-hoc commands). nullOnDelete so pruning old runs
            // keeps their calls in the day-range usage totals.
            $table->foreignId('run_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llm_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('run_id');
        });
    }
};
