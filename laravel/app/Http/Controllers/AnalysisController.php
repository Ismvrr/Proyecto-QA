<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AnalysisController extends Controller
{
    public function history()
    {
        $company = Auth::user()->company;
        if (!$company) {
            return response()->json(['analyses' => [], 'total_tokens' => 0]);
        }

        return response()->json([
            'total_tokens' => (int) DB::table('analysis_jobs')
                ->where('company_id', $company->id)
                ->where('status', 'completed')
                ->sum('gemini_tokens_used'),
            'analyses' => DB::table('analysis_jobs')
                ->where('company_id', $company->id)
                ->orderByDesc('id')
                ->limit(20)
                ->get([
                    'id', 'year', 'month', 'status', 'gemini_key_source',
                    'gemini_tokens_used', 'input_tokens', 'output_tokens',
                    'started_at', 'completed_at',
                ]),
        ]);
    }

    public function historyDetail(int $id)
    {
        $company = Auth::user()->company;
        $job = $company
            ? DB::table('analysis_jobs')
                ->where('company_id', $company->id)
                ->where('id', $id)
                ->first([
                    'id', 'year', 'month', 'status', 'prompt_snapshot',
                    'prompt1_result', 'prompt2_result', 'gemini_key_source',
                    'gemini_tokens_used', 'input_tokens', 'output_tokens',
                    'started_at', 'completed_at', 'error_message',
                ])
            : null;

        if (!$job) {
            return response()->json(['message' => 'Análisis no encontrado.'], 404);
        }

        $decodeResult = static function (?string $value): ?string {
            if (!$value) {
                return null;
            }
            $decoded = json_decode($value, true);
            return is_array($decoded) ? ($decoded['text'] ?? null) : $value;
        };

        return response()->json([
            'id' => $job->id,
            'year' => $job->year,
            'month' => $job->month,
            'status' => $job->status,
            'source' => $job->gemini_key_source,
            'prompt' => $job->prompt_snapshot,
            'result' => $decodeResult($job->prompt1_result),
            'consolidation' => $decodeResult($job->prompt2_result),
            'tokens' => [
                'input' => $job->input_tokens,
                'output' => $job->output_tokens,
                'total' => $job->gemini_tokens_used,
            ],
            'error' => $job->error_message,
            'started_at' => $job->started_at,
            'completed_at' => $job->completed_at,
        ]);
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
            'api_key' => ['nullable', 'string', 'min:20', 'max:500'],
        ]);
        $company = Auth::user()->company;

        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $baseUrl = config('services.fastapi.url', 'http://127.0.0.1:8000');
        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders(array_filter(['X-Gemini-API-Key' => $data['api_key'] ?? null]))
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
            'consolidate' => ['sometimes', 'boolean'],
            'api_key' => ['nullable', 'string', 'min:20', 'max:500'],
        ]);
        $company = Auth::user()->company;
        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $baseUrl = config('services.fastapi.url', 'http://127.0.0.1:8000');
        $response = Http::baseUrl($baseUrl)
            ->acceptJson()
            ->withHeaders(array_filter(['X-Gemini-API-Key' => $data['api_key'] ?? null]))
            ->withCookies(['token' => request()->cookie('token')], parse_url($baseUrl, PHP_URL_HOST))
            ->post('/api/analyze/period', [
                'company_id' => $company->id,
                'year' => $data['year'],
                'month' => $data['month'],
                'client_prompt_id' => $data['client_prompt_id'] ?? null,
                'prompt_text' => $data['prompt_text'],
                'max_conversations' => $data['max_conversations'] ?? 1,
                'consolidate' => $data['consolidate'] ?? false,
            ]);

        return response()->json($response->json(), $response->status());
    }
}
