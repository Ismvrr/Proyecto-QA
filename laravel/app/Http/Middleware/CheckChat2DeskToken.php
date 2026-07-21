<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckChat2DeskToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Solo validamos si es Admin y tiene una compañía vinculada
        if ($user && $user->role === 'admin' && $user->company) {
            
            $sessionKey = 'c2d_token_valid_until';

            // Si la validación en sesión ya expiró o no existe
            if (!session()->has($sessionKey) || now()->gt(session($sessionKey))) {
                
                $response = Http::withHeaders([
                    'Authorization' => $user->company->api_token
                ])->get('https://api.chat2desk.com.mx/v1/companies/api_info');

                if ($response->unauthorized() || $response->json('status') === 'error') {
                    // Si falla, notificamos a la vista
                    view()->share('token_error', true);
                    // Opcional: Marcar la empresa en la DB
                    $user->company->update(['status' => 'token_error']);
                } else {
                    // Si es exitoso, no volvemos a preguntar en 1 hora
                    session([$sessionKey => now()->addHour()]);
                    $user->company->update(['status' => 'active']);
                }
            }
        }

        return $next($request);
    }
}