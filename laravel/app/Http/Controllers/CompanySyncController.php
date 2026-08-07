<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\User;

class CompanySyncController extends Controller
{
    // Paso 1: Validar Token y Guardar Empresa
    public function syncToken(Request $request)
    {
        $request->validate(['api_token' => 'required|string']);
        $token = $request->api_token;
        $user = Auth::user();
        $role = $user->role;

        if (!in_array($role, ['admin', 'supervisor', 'shadow'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Solo administradores y supervisores pueden conectar Chat2Desk.',
            ], 403);
        }

        $response = Http::timeout(20)->withHeaders(['Authorization' => $token])
            ->get('https://api.chat2desk.com.mx/v1/companies/api_info');

        $payload = $response->json();
        if (!$response->successful() || ($payload['status'] ?? null) !== 'success') {
            Log::warning('Chat2Desk company sync rejected token', [
                'http_status' => $response->status(),
                'c2d_status' => $payload['status'] ?? null,
                'error' => $payload['error'] ?? $payload['errors'] ?? null,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $payload['message'] ?? $payload['error'] ?? 'Chat2Desk rechazó el token.',
            ], 400);
        }

        $data = $payload['data'] ?? [];
        if (empty($data['companyID']) || empty($data['company_name'])) {
            Log::error('Chat2Desk company sync returned incomplete data', [
                'http_status' => $response->status(),
                'keys' => array_keys($data),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Chat2Desk devolvió una respuesta incompleta de empresa.',
            ], 502);
        }

        $company = $user->company;
        $isBootstrap = !$company;

        if (!$isBootstrap && (string) $company->company_id !== (string) $data['companyID']) {
            return response()->json([
                'status' => 'error',
                'message' => 'El token no corresponde a la empresa vinculada a este usuario.',
            ], 403);
        }

        $company = Company::updateOrCreate(
            ['company_id' => $data['companyID']],
            [
                'name' => $data['company_name'],
                'api_token' => $token,
                // FastAPI valida el webhook con este hash sin leer el token cifrado.
                'api_token_hash' => hash('sha256', $token),
                'status' => 'active',
                'remote_id' => $data['companyID'],
                'partner_id' => $data['partnerID'],
                'company_mode' => $data['company_mode'],
                'lang' => $data['company_lang'],
                'last_sync_at' => now(),
            ]
        );

        // Keep a first-time user as shadow until operator sync confirms the
        // role returned by Chat2Desk.
        $user->update(['company_id' => $company->id]);

        return response()->json(['status' => 'success', 'company_id' => $company->id]);
    }

    // Paso 2: Sincronización Masiva de Operadores
    public function syncOperators(Request $request)
    {
        if (!in_array(Auth::user()->role, ['admin', 'supervisor', 'shadow'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Solo administradores y supervisores pueden sincronizar operadores.',
            ], 403);
        }

        $company = Auth::user()->company;
        
        if (!$company) {
            return response()->json(['status' => 'error', 'message' => 'No hay compañía vinculada.'], 400);
        }

        $roles = ['admin', 'supervisor', 'operator'];
        $totalProcessed = 0;

        foreach ($roles as $role) {
            $offset = 0;
            $limit = 20;

            do {
                $response = Http::timeout(20)->withHeaders(['Authorization' => $company->api_token])
                    ->get("https://api.chat2desk.com.mx/v1/operators/", [
                        'status' => 'enabled',
                        'role' => $role,
                        'limit' => $limit,
                        'offset' => $offset
                    ]);

                $resData = $response->json();
                $operators = $resData['data'] ?? [];

		foreach ($operators as $op) {
    		// CAMBIO: Buscamos por EMAIL para evitar el error de duplicado
    		User::updateOrCreate(
        		['email' => $op['email']], 
        		[
            		    'c2d_user_id'     => $op['id'], // Actualizamos el ID de C2D si no lo tenía
            		    'company_id'      => $company->id,
            		    'first_name'      => $op['first_name'],
            		    'last_name'       => $op['last_name'],
            		    'phone'           => $op['phone'] ?? null,
            		    'avatar'          => $op['avatar'] ?? null,
            		    'role'            => $op['role'],
            		    'access_right_id' => $op['access_right_id'] ?? null,
            		    'status'          => 'enabled',
                            'last_visit'      => isset($op['last_visit']) ? date('Y-m-d H:i:s', strtotime($op['last_visit'])) : null,
          		 ]
    		);
    		$totalProcessed++;
	}	



                $offset += $limit;
                $hasMore = count($operators) >= $limit;
            } while ($hasMore);
        }

        $currentUser = Auth::user()->fresh();
        if ($currentUser->role === 'shadow') {
            // A first connection must prove that the logged-in C2D user is
            // an admin or supervisor before retaining the company token.
            $company->update([
                'api_token' => null,
                'api_token_hash' => null,
                'status' => 'unconfigured',
            ]);
            $currentUser->update(['company_id' => null]);

            return response()->json([
                'status' => 'error',
                'message' => 'El usuario C2D no tiene rol de administrador o supervisor.',
            ], 403);
        }

        return response()->json(['status' => 'success', 'total' => $totalProcessed]);
    }

    /**
     * Activa o desactiva el modo real-time para la empresa del usuario.
     * La activación en Chat2Desk sigue siendo un paso separado por cuenta.
     */
    public function updateRealtime(Request $request)
    {
        if (!in_array(Auth::user()->role, ['admin', 'supervisor'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Solo administradores y supervisores pueden configurar real-time.',
            ], 403);
        }

        $request->validate(['enabled' => 'required|boolean']);
        $company = Auth::user()->company;

        if (!$company) {
            return response()->json(['status' => 'error', 'message' => 'No hay compañía vinculada.'], 400);
        }

        $company->update(['realtime_enabled' => $request->boolean('enabled')]);

        return response()->json([
            'status' => 'success',
            'realtime_enabled' => $company->realtime_enabled,
            'message' => $company->realtime_enabled
                ? 'Real-time activado en API_C2D. Falta apuntar esta cuenta en Chat2Desk.'
            : 'Real-time desactivado en API_C2D.',
            ]);
    }

}
