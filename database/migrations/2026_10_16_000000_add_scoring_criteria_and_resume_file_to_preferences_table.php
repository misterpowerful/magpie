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
        Schema::table('preferences', function (Blueprint $table) {
            // What the scorer judges on, in the owner's words. Null means
            // "use Preference::DEFAULT_SCORING_CRITERIA".
            $table->text('scoring_criteria')->nullable()->after('exclude_stack');

            // The uploaded resume file. resume_text (extracted, then
            // optionally hand-corrected) is what the scorer actually reads.
            $table->string('resume_path')->nullable()->after('resume_hash');
            $table->string('resume_filename')->nullable()->after('resume_path');
            $table->timestamp('resume_uploaded_at')->nullable()->after('resume_filename');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('preferences', function (Blueprint $table) {
            $table->dropColumn(['scoring_criteria', 'resume_path', 'resume_filename', 'resume_uploaded_at']);
        });
    }
};
