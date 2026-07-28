<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ExtractionController extends Controller
{
    /**
     * Starts FastAPI extraction without exposing the C2D token to the browser.
     */
    public function start(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'exclude_autoreply' => ['sometimes', 'boolean'],
        ]);

        $company = Auth::user()->company;
        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $response = $this->fastApiRequest('post', '/api/extract', [
            'company_id' => $company->id,
            'year' => $data['year'],
            'month' => $data['month'],
            'c2d_token' => $company->api_token,
            'exclude_autoreply' => $data['exclude_autoreply'] ?? false,
        ]);

        return response()->json($response->json(), $response->status());
    }

    /** Returns synchronization periods for the authenticated company. */
    public function status()
    {
        $company = Auth::user()->company;
        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $response = $this->fastApiRequest(
            'get',
            '/api/sync/status?company_id=' . $company->id
        );

        return response()->json($response->json(), $response->status());
    }

    /** Returns the extracted messages for a selected period. */
    public function messages(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'dialog_id' => ['nullable', 'string', 'max:50'],
            'request_id' => ['nullable', 'string', 'max:50'],
            'client_id' => ['nullable', 'string', 'max:50'],
            'message_type' => ['nullable', 'string', 'max:30'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $company = Auth::user()->company;

        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $response = $this->fastApiRequest(
            'get',
            '/api/messages?' . http_build_query(array_filter([
                'company_id' => $company->id,
                'year' => $data['year'],
                'month' => $data['month'],
                'dialog_id' => $data['dialog_id'] ?? null,
                'request_id' => $data['request_id'] ?? null,
                'client_id' => $data['client_id'] ?? null,
                'message_type' => $data['message_type'] ?? null,
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
            ], fn ($value) => $value !== null && $value !== '') )
        );

        return response()->json($response->json(), $response->status());
    }

    /** Returns paginated conversations grouped by dialog. */
    public function conversations(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $company = Auth::user()->company;

        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $response = $this->fastApiRequest('get', '/api/conversations?' . http_build_query([
            'company_id' => $company->id,
            'year' => $data['year'],
            'month' => $data['month'],
            'page' => $data['page'] ?? 1,
        ]));

        return response()->json($response->json(), $response->status());
    }

    /** Returns one conversation timeline. */
    public function conversationDetail(Request $request, string $dialogId)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'request_id' => ['nullable', 'string', 'max:50'],
        ]);
        $company = Auth::user()->company;

        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $response = $this->fastApiRequest('get', '/api/conversations/' . rawurlencode($dialogId) . '?' . http_build_query([
            'company_id' => $company->id,
            'year' => $data['year'],
            'month' => $data['month'],
            'request_id' => $data['request_id'] ?? null,
        ]));

        return response()->json($response->json(), $response->status());
    }

    private function fastApiRequest(string $method, string $uri, array $payload = [])
    {
        $request = Http::baseUrl(config('services.fastapi.url', 'http://127.0.0.1:8000'))
            ->acceptJson()
            ->withCookies(
                ['token' => request()->cookie('token')],
                parse_url(config('services.fastapi.url', 'http://127.0.0.1:8000'), PHP_URL_HOST)
            );

        return $method === 'post'
            ? $request->post($uri, $payload)
            : $request->get($uri);
    }
}
