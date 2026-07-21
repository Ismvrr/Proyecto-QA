<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class SyncChat2DeskOperators extends Command
{
    // Nombre del comando en la terminal
    protected $signature = 'c2d:sync-operators';
    protected $description = 'Sincroniza los operadores de Chat2Desk cada 15 minutos';

    public function handle()
    {
        $companies = Company::where('status', 'active')->whereNotNull('api_token')->get();

        foreach ($companies as $company) {
            $this->info("Sincronizando compañía: {$company->name}");
            $roles = ['admin', 'supervisor', 'operator'];
            
            // 1. Iniciamos lista de IDs encontrados en esta vuelta
            $currentSyncedIds = [];

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

                    if ($response->successful()) {
                        $operators = $response->json('data') ?? [];
                        
                        foreach ($operators as $op) {
                            // Actualizamos o creamos al usuario
                            $user = User::updateOrCreate(
                                ['email' => $op['id']], 
                                [
                                    'c2d_user_id'     => $op['id'],
                                    'company_id'      => $company->id,
                                    'first_name'      => $op['first_name'],
                                    'last_name'       => $op['last_name'],
                                    'role'            => $op['role'],
                                    'status'          => 'enabled', // Forzamos que esté activo si vino en la API
                                    'last_visit'      => isset($op['last_visit']) ? date('Y-m-d H:i:s', strtotime($op['last_visit'])) : null,
                                ]
                            );
                            
                            // 2. Guardamos el ID externo para la limpieza posterior
                            $currentSyncedIds[] = $op['id'];
                        }
                        $offset += $limit;
                        $hasMore = count($operators) >= $limit;
                    } else {
                        $hasMore = false;
                        $this->error("Fallo en API para rol {$role} en {$company->name}");
                    }
                } while ($hasMore);
            }

            // 3. FASE DE LIMPIEZA:
            // Deshabilitamos a todos los usuarios de ESTA compañía que NO aparecieron en la API
            // Protegemos a los usuarios con rol 'shadow' (administradores del sistema OnCloud)
            if (!empty($currentSyncedIds)) {
                $disabledCount = User::where('company_id', $company->id)
                    ->whereNotIn('c2d_user_id', $currentSyncedIds)
                    ->where('role', '!=', 'shadow') 
                    ->where('status', 'enabled')
                    ->update(['status' => 'disabled']);
                
                if ($disabledCount > 0) {
                    $this->warn("Compañía {$company->name}: Se deshabilitaron {$disabledCount} usuarios que ya no están en Chat2Desk.");
                }
            }
        }
        $this->info('Sincronización finalizada con éxito.');
    }    

}
