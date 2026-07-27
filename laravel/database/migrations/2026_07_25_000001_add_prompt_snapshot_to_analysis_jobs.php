<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->text('prompt_snapshot')->nullable()->after('client_prompt_id');
            $table->integer('input_tokens')->default(0)->after('gemini_tokens_used');
            $table->integer('output_tokens')->default(0)->after('input_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->dropColumn(['prompt_snapshot', 'input_tokens', 'output_tokens']);
        });
    }
};
