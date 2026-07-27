<?php

namespace App\Http\Controllers;

use App\Models\ClientPrompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PromptController extends Controller
{
    public function index()
    {
        $company = Auth::user()->company;
        $templates = DB::table('prompt_templates')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'prompt_text'])
            ->map(fn ($prompt) => [
                'key' => 'template-' . $prompt->id,
                'id' => $prompt->id,
                'source' => 'template',
                'name' => '[Plantilla] ' . $prompt->name,
                'description' => $prompt->description,
                'prompt_text' => $prompt->prompt_text,
            ]);

        $clientPrompts = $company
            ? ClientPrompt::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'prompt_text'])
                ->map(fn ($prompt) => [
                    'key' => 'client-' . $prompt->id,
                    'id' => $prompt->id,
                    'source' => 'client',
                    'name' => $prompt->name,
                    'prompt_text' => $prompt->prompt_text,
                ])
            : collect();

        return response()->json(['prompts' => $templates->concat($clientPrompts)->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'prompt_text' => ['required', 'string', 'min:10', 'max:12000'],
        ]);
        $company = Auth::user()->company;

        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        // Reusing a name updates the company's prompt instead of creating duplicates.
        $prompt = ClientPrompt::updateOrCreate(
            ['company_id' => $company->id, 'name' => $data['name']],
            ['prompt_text' => $data['prompt_text'], 'is_active' => true],
        );

        return response()->json(['prompt' => $prompt]);
    }
}
