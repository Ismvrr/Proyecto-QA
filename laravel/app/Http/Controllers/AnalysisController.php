<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class AnalysisController extends Controller
{
    public function geminiStatus()
    {
        return response()->json(['configured' => !empty(Auth::user()->company?->gemini_api_key)]);
    }

    public function saveGeminiKey(Request $request)
    {
        $data = $request->validate(['api_key' => ['required', 'string', 'min:20', 'max:500']]);
        $company = Auth::user()->company;
        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $company->update(['gemini_api_key' => $data['api_key']]);
        return response()->json(['status' => 'success', 'message' => 'API key guardada de forma segura.']);
    }

    public function conversation(Request $request)
    {
        $data = $request->validate([
            'dialog_id' => ['required', 'string', 'max:50'],
            'request_id' => ['nullable', 'string', 'max:50'],
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'client_prompt_id' => ['nullable', 'integer'],
            'prompt_text' => ['required', 'string', 'min:10', 'max:12000'],
        ]);
        $company = Auth::user()->company;

        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $baseUrl = config('services.fastapi.url', 'http://127.0.0.1:8000');
        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders(array_filter(['X-Gemini-API-Key' => $company->gemini_api_key]))
            ->withCookies(['token' => request()->cookie('token')], parse_url($baseUrl, PHP_URL_HOST))
            ->post('/api/analyze/conversation', [
                'company_id' => $company->id,
                'dialog_id' => $data['dialog_id'],
                'request_id' => $data['request_id'] ?? null,
                'year' => $data['year'],
                'month' => $data['month'],
                'client_prompt_id' => $data['client_prompt_id'] ?? null,
                'prompt_text' => $data['prompt_text'],
            ]);

        return response()->json($response->json(), $response->status());
    }

    public function period(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'client_prompt_id' => ['nullable', 'integer'],
            'prompt_text' => ['required', 'string', 'min:10', 'max:12000'],
            'max_conversations' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $company = Auth::user()->company;
        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $baseUrl = config('services.fastapi.url', 'http://127.0.0.1:8000');
        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders(array_filter(['X-Gemini-API-Key' => $company->gemini_api_key]))
            ->withCookies(['token' => request()->cookie('token')], parse_url($baseUrl, PHP_URL_HOST))
            ->post('/api/analyze/period', [
                'company_id' => $company->id,
                'year' => $data['year'],
                'month' => $data['month'],
                'client_prompt_id' => $data['client_prompt_id'] ?? null,
                'prompt_text' => $data['prompt_text'],
                'max_conversations' => $data['max_conversations'] ?? 1,
            ]);

        return response()->json($response->json(), $response->status());
    }
}
