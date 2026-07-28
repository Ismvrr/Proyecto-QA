<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('analysis_jobs', 'gemini_key_source')) {
            Schema::table('analysis_jobs', function (Blueprint $table) {
                $table->string('gemini_key_source', 10)->default('server')->after('prompt_snapshot');
                $table->index(['company_id', 'gemini_key_source', 'started_at'], 'idx_server_key_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('analysis_jobs', 'gemini_key_source')) {
            Schema::table('analysis_jobs', function (Blueprint $table) {
                $table->dropIndex('idx_server_key_rate');
                $table->dropColumn('gemini_key_source');
            });
        }
    }
};
