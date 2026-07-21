<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Models\Company;
use App\Models\User;

class CompanySyncController extends Controller
{
    // Paso 1: Validar Token y Guardar Empresa
    public function syncToken(Request $request)
    {
        $request->validate(['api_token' => 'required|string']);
        $token = $request->api_token;

        $response = Http::withHeaders(['Authorization' => $token])
            ->get('https://api.chat2desk.com.mx/v1/companies/api_info');

        if (!$response->successful() || $response->json('status') !== 'success') {
            return response()->json(['status' => 'error', 'message' => 'Token inválido.'], 400);
        }

        $data = $response->json('data');

        // Validación: Solo el administrador puede conectar
        if (Auth::user()->email !== $data['admin_email']) {
            return response()->json(['status' => 'error', 'message' => 'Solo el administrador puede conectar Chat2desk.'], 403);
        }

        $company = Company::updateOrCreate(
            ['company_id' => $data['companyID']],
            [
                'name' => $data['company_name'],
                'api_token' => $token,
                'status' => 'active',
                'remote_id' => $data['companyID'],
                'partner_id' => $data['partnerID'],
                'company_mode' => $data['company_mode'],
                'lang' => $data['company_lang'],
                'last_sync_at' => now(),
            ]
        );

        Auth::user()->update(['company_id' => $company->id, 'role' => 'admin']);

        return response()->json(['status' => 'success', 'company_id' => $company->id]);
    }

    // Paso 2: Sincronización Masiva de Operadores
    public function syncOperators(Request $request)
    {
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
                $response = Http::withHeaders(['Authorization' => $company->api_token])
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

        return response()->json(['status' => 'success', 'total' => $totalProcessed]);
    }
}
