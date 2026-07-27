<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('prompt_templates')->insertOrIgnore([
            [
                'name' => 'Análisis conversacional adaptable',
                'type' => 'custom',
                'description' => 'Plantilla base para cualquier industria. Debe personalizarse antes de usar.',
                'prompt_text' => "Actúa como analista de conversaciones para {{industry}}.\n\nContexto de la empresa: {{company_context}}\nObjetivo del análisis: {{analysis_objective}}\nCriterios de éxito: {{success_criteria}}\nCampos que necesito extraer: {{fields_to_extract}}\n\nAnaliza la conversación al final. No inventes datos. Separa hechos, inferencias y recomendaciones. Entrega resumen, intención, etapa del cliente, objeciones, resultado y recomendaciones accionables.\n\nCONVERSACIÓN:\n{{conversation}}",
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Análisis de flujo de bot adaptable',
                'type' => 'bot_analysis',
                'description' => 'Plantilla para evaluar un bot. Reemplaza el flujo y criterios de la empresa.',
                'prompt_text' => "Actúa como especialista en experiencia conversacional para {{industry}}.\n\nContexto: {{company_context}}\nFlujo esperado del bot: {{bot_flow}}\nObjetivo de negocio: {{analysis_objective}}\n\nEvalúa si el bot siguió el flujo esperado, si respondió de forma pertinente y en qué punto avanzó o abandonó el cliente. Entrega punto del flujo, pertinencia, fricciones, interés, satisfacción y acción recomendada.\n\nCONVERSACIÓN:\n{{conversation}}",
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('prompt_templates')
            ->whereIn('name', ['Análisis conversacional adaptable', 'Análisis de flujo de bot adaptable'])
            ->delete();
    }
};
