<?php
use App\Http\Controllers\Auth\Chat2DeskLoginController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanySyncController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ExtractionController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\AnalysisController;

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
        // Sin empresa vinculada debe mostrarse el formulario de conexión.
        'company_status' => $company ? $company->status : 'unconfigured',
        'realtime_enabled' => $company ? $company->realtime_enabled : false,
        'can_connect_c2d' => in_array($user->role, ['admin', 'supervisor', 'shadow'], true),
        'webhook_url' => url('/api/webhooks/c2d'),
    ]);
    })->middleware(['auth'])->name('dashboard');

    Route::post('/logout', [Chat2DeskLoginController::class, 'logout'])->name('logout');

    // Configuración y Sincronización
    Route::prefix('config')->group(function () {
        Route::post('/sync-token', [CompanySyncController::class, 'syncToken'])->name('config.sync.token');
        Route::post('/sync-operators', [CompanySyncController::class, 'syncOperators'])->name('config.sync.operators');
        Route::patch('/realtime', [CompanySyncController::class, 'updateRealtime'])->name('config.realtime');
        Route::post('/extract', [ExtractionController::class, 'start'])->name('config.extract');
        Route::get('/sync-status', [ExtractionController::class, 'status'])->name('config.sync.status');
        Route::get('/messages', [ExtractionController::class, 'messages'])->name('config.messages');
        Route::get('/conversations', [ExtractionController::class, 'conversations'])->name('config.conversations');
        Route::get('/conversations/{dialogId}', [ExtractionController::class, 'conversationDetail'])->name('config.conversation.detail');
        Route::get('/prompts', [PromptController::class, 'index'])->name('config.prompts');
        Route::post('/prompts', [PromptController::class, 'store'])->name('config.prompts.store');
        Route::get('/analysis-history', [AnalysisController::class, 'history'])->name('config.analysis.history');
        Route::get('/analysis-history/{id}', [AnalysisController::class, 'historyDetail'])->name('config.analysis.history.detail');
        Route::post('/analyze/conversation', [AnalysisController::class, 'conversation'])->name('config.analyze.conversation');
        Route::post('/analyze/period', [AnalysisController::class, 'period'])->name('config.analyze.period');
    });

    Route::get('/reports/operators', [ReportController::class, 'operators'])->name('reports.operators');
    Route::get('/reports/monthly', [ReportController::class, 'monthly'])->name('reports.monthly');
    Route::get('/reports/analysis/{jobId}', [ReportController::class, 'analysisJob'])->name('reports.analysis.job');
});
