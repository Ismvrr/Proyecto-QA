<?php
use App\Http\Controllers\Auth\Chat2DeskLoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanySyncController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ReportController;

Route::redirect('/', '/login');

// Rutas de Invitado
Route::middleware('guest')->group(function () {
    Route::get('/login', [Chat2DeskLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [Chat2DeskLoginController::class, 'login']);
});

// Rutas Protegidas (Sprint 2 y 3)
Route::middleware('auth')->group(function () {
    
    // Dashboard con detección de estatus de empresa
    Route::get('/dashboard', function () {
    $user = Auth::user();
    $company = $user->company;
    
    // Obtenemos los conteos reales de la DB para la gráfica
    $stats = \App\Models\User::where('company_id', $user->company_id)
            ->where('status', 'enabled') // <--- CRÍTICO: Solo los activos
            ->where('role', '!=', 'shadow') // <--- No contar al Admin del Sistema
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->get();

    return view('dashboard', [
        'stats' => $stats,
        'company_status' => $company ? $company->status : 'active',
        'realtime_enabled' => $company ? $company->realtime_enabled : false,
        'webhook_url' => url('/api/webhooks/c2d'),
    ]);
    })->middleware(['auth'])->name('dashboard');

    Route::post('/logout', [Chat2DeskLoginController::class, 'logout'])->name('logout');

    // Configuración y Sincronización
    Route::prefix('config')->group(function () {
        Route::post('/sync-token', [CompanySyncController::class, 'syncToken'])->name('config.sync.token');
        Route::post('/sync-operators', [CompanySyncController::class, 'syncOperators'])->name('config.sync.operators');
        Route::patch('/realtime', [CompanySyncController::class, 'updateRealtime'])->name('config.realtime');
    });

    Route::get('/reports/operators', [ReportController::class, 'operators'])->name('reports.operators');
});
