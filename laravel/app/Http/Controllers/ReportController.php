<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ReportController extends Controller
{
    public function operators()
    {
        $user = Auth::user();
        
        // Contamos cuántos usuarios hay por cada rol
        $stats = User::where('company_id', $user->company_id)
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->get();

        return view('reports.operators', compact('stats'));
    }

    public function monthly(Request $request)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        $company = Auth::user()->company;
        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $baseUrl = config('services.fastapi.url', 'http://127.0.0.1:8000');
        $response = Http::baseUrl($baseUrl)
            ->accept('application/pdf')
            ->withCookies(['token' => request()->cookie('token')], parse_url($baseUrl, PHP_URL_HOST))
            ->get('/api/reports/monthly?' . http_build_query([
                'company_id' => $company->id,
                'year' => $data['year'],
                'month' => $data['month'],
            ]));

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $response->header('Content-Disposition', 'attachment'));
    }

    public function analysisJob(int $jobId)
    {
        $company = Auth::user()->company;
        if (!$company) {
            return response()->json(['message' => 'No hay compañía vinculada.'], 400);
        }

        $baseUrl = config('services.fastapi.url', 'http://127.0.0.1:8000');
        $response = Http::baseUrl($baseUrl)
            ->accept('application/pdf')
            ->withCookies(['token' => request()->cookie('token')], parse_url($baseUrl, PHP_URL_HOST))
            ->get('/api/reports/job/' . $jobId . '?' . http_build_query([
                'company_id' => $company->id,
            ]));

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', $response->header('Content-Disposition', 'attachment'));
    }
}
