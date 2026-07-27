<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function fullPrompt(string $file, bool $adaptCompanyReferences = false): string
    {
        $prompt = file_get_contents(resource_path('prompts/' . $file));

        if ($adaptCompanyReferences) {
            $prompt = str_replace(
                ['HISSPAREP', 'Hisparep', 'hisparep'],
                ['{{company_name}}', '{{company_name}}', '{{company_name}}'],
                $prompt,
            );
            $prompt = preg_replace(
                '/Servicios principales:.*?La clínica a decidido/iu',
                "Servicios principales: {{company_services}}. Modelo de ingresos: {{business_model}}. Contexto de campañas: {{campaign_context}}. La empresa ha decidido",
                $prompt,
            );
        }

        return "NOTA DE ADAPTACIÓN\nAntes de usar esta plantilla, reemplaza únicamente las variables de contexto de tu empresa: {{company_name}}, {{company_context}}, {{company_services}}, {{business_model}}, {{campaign_context}}, {{bot_flow}} y {{channels}}. Conserva y adapta la metodología, criterios y entregables según tus necesidades.\n\n" . $prompt;
    }

    public function up(): void
    {
        $templates = [
            [
                'name' => 'Bot - Análisis completo de flujo',
                'type' => 'bot_analysis',
                'description' => 'Prompt completo original para evaluar el flujo de un bot. Adaptar el contexto de cada empresa.',
                'prompt_text' => $this->fullPrompt('bot_analysis_full.txt'),
            ],
            [
                'name' => 'Ads - Análisis de leads por campaña',
                'type' => 'ads_analysis',
                'description' => 'Prompt completo para clasificar leads, campañas, tiempos, objeciones y conversión.',
                'prompt_text' => $this->fullPrompt('ads_lead_analysis_full.txt', true),
            ],
            [
                'name' => 'Ads - Informe ejecutivo y nueva campaña',
                'type' => 'ads_report',
                'description' => 'Prompt completo para informe ejecutivo, mejora del bot y diseño de campaña.',
                'prompt_text' => $this->fullPrompt('ads_executive_full.txt', true),
            ],
        ];

        foreach ($templates as $template) {
            DB::table('prompt_templates')->updateOrInsert(
                ['name' => $template['name']],
                array_merge($template, [
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]),
            );
        }
    }

    public function down(): void
    {
        DB::table('prompt_templates')->whereIn('name', [
            'Bot - Análisis completo de flujo',
            'Ads - Análisis de leads por campaña',
            'Ads - Informe ejecutivo y nueva campaña',
        ])->delete();
    }
};
