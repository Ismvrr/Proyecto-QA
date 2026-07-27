<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('prompt_templates')->whereIn('name', [
            'Análisis conversacional adaptable',
            'Análisis de flujo de bot adaptable',
        ])->delete();
    }

    public function down(): void
    {
        // The previous short templates are intentionally not restored.
    }
};
