<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | C2D OnCloud Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@700&family=Open+Sans:wght@400;700&display=swap');
        body { font-family: 'Open Sans', sans-serif; color: #212121; }
        .font-nunito { font-family: 'Nunito', sans-serif; }
        .bg-c2d-dark-blue { background-color: #0A3656; } 
        .bg-c2d-pale-blue { background-color: #CDD8E6; }
        .bg-c2d-blue { background-color: #205B9B; }
        .text-c2d-dark-blue { color: #0A3656; }
        .iframe-container { height: calc(100vh - 64px); }
        .sidebar-transition { transition: width 0.3s ease; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-100 flex overflow-hidden" 
      x-data="{ 
        activeApp: 'c2d', 
        activeModule: 'chats', 
        sidebarOpen: true, 
        showConfigModal: false,
        openMenus: { dashboard: false, config: false },
        isSyncing: false,
        progress: 0,
         syncMessage: 'Iniciando...',
         stats: {{ json_encode($stats ?? []) }},
         companyStatus: '{{ $company_status ?? 'shadow' }}',
         realtimeEnabled: {{ ($realtime_enabled ?? false) ? 'true' : 'false' }},
         realtimeSaving: false,
         realtimeMessage: '',

        init() {
            this.$watch('activeModule', value => {
                if (value === 'users' && this.stats.length > 0) {
                    this.$nextTick(() => this.renderChart());
                }
            });
        },

        renderChart() {
            const canvas = document.getElementById('rolesChartBranded');
            if (!canvas) return;
            if (window.myChart) window.myChart.destroy();
            const ctx = canvas.getContext('2d');
            window.myChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: this.stats.map(s => s.role),
                    datasets: [{
                        data: this.stats.map(s => s.total),
                        backgroundColor: ['#205B9B', '#8FABD9', '#0A3656'],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } } 
                }
            });
        },

         async startSync() {
            const tokenInput = document.getElementById('api_token_input');
            if (!tokenInput.value) return alert('Por favor, ingresa un token.');
            this.isSyncing = true;
            this.progress = 10;
            this.syncMessage = 'Validando conexión...';
            try {
                let response = await fetch('{{ route('config.sync.token') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ api_token: tokenInput.value })
                });
                let result = await response.json();
                if (result.status === 'error') throw new Error(result.message);
                this.progress = 40;
                this.syncMessage = 'Sincronizando operadores...';
                let syncRes = await fetch('{{ route('config.sync.operators') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                });
                if ((await syncRes.json()).status === 'success') {
                    this.progress = 100;
                    this.syncMessage = '¡Éxito!';
                    setTimeout(() => window.location.reload(), 1000);
                }
            } catch (error) {
                alert('Error: ' + error.message);
                this.isSyncing = false;
                this.progress = 0;
            }
         }
         ,

         async toggleRealtime() {
             this.realtimeSaving = true;
             this.realtimeMessage = '';
             try {
                 const response = await fetch('{{ route('config.realtime') }}', {
                     method: 'PATCH',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}'
                     },
                     body: JSON.stringify({ enabled: this.realtimeEnabled })
                 });
                 const result = await response.json();
                 if (!response.ok || result.status !== 'success') {
                     throw new Error(result.message || 'No se pudo actualizar real-time.');
                 }
                 this.realtimeMessage = result.message;
             } catch (error) {
                 this.realtimeEnabled = !this.realtimeEnabled;
                 this.realtimeMessage = error.message;
             } finally {
                 this.realtimeSaving = false;
             }
         }
       }">

    <nav class="w-16 bg-c2d-dark-blue h-screen flex flex-col items-center py-4 z-30 shadow-2xl shrink-0">
        <button @click="activeApp = 'c2d'; sidebarOpen = true" class="w-12 h-12 rounded-xl mb-4 flex items-center justify-center bg-c2d-blue text-white shadow-lg">
            <span class="text-2xl">☁️</span>
        </button>
        <button class="w-12 h-12 rounded-xl mb-4 flex items-center justify-center text-slate-400 hover:text-white transition-colors">
            <span class="text-2xl">📞</span>
        </button>
    </nav>

    <aside :class="sidebarOpen ? 'w-64' : 'w-0'" class="bg-c2d-pale-blue text-c2d-dark-blue h-screen flex flex-col sidebar-transition relative z-20 shrink-0 overflow-hidden shadow-xl">
        <div class="h-16 flex items-center justify-between px-4 border-b border-blue-200 min-w-[16rem]">
            <span class="font-nunito font-bold text-lg">C2D OnCloud</span>
            <button @click="sidebarOpen = false" class="p-1 hover:bg-blue-200 rounded transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>

        <nav class="flex-1 flex flex-col py-4 min-w-[16rem]">
            <ul class="space-y-1 px-2">
                
                <li @click="activeModule = 'chats'" 
                    :class="activeModule === 'chats' ? 'bg-c2d-blue text-white shadow-md' : 'hover:bg-blue-200'" 
                    class="flex items-center p-2 rounded-xl cursor-pointer transition-all font-nunito mb-2">
                    <span class="text-xl">💬</span> <span class="ml-3 font-bold">Chats Activos</span>
                </li>

                <li>
                    <button @click="openMenus.dashboard = !openMenus.dashboard" 
                            class="w-full flex items-center justify-between p-2 text-c2d-dark-blue font-nunito hover:bg-blue-200 rounded-xl transition-colors">
                        <div class="flex items-center">
                            <span class="text-xl">📊</span> 
                            <span class="ml-3 font-bold uppercase text-[10px] tracking-[0.2em]">Dashboard</span>
                        </div>
                        <svg class="w-4 h-4 transition-transform duration-200" 
                            :class="openMenus.dashboard ? 'rotate-180' : ''" 
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    
                    <ul x-show="openMenus.dashboard" 
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        class="mt-1 space-y-1">
                        <li @click="activeModule = 'users'" 
                            :class="activeModule === 'users' ? 'text-c2d-blue font-bold border-l-2 border-c2d-blue' : 'text-slate-600 hover:text-c2d-blue'" 
                            class="ml-10 p-2 rounded-lg cursor-pointer transition-all text-sm font-medium">
                            Usuarios
                        </li>
                    </ul>
                </li>

                <li class="pt-2">
                    <button @click="openMenus.config = !openMenus.config" 
                            class="w-full flex items-center justify-between p-2 text-c2d-dark-blue font-nunito hover:bg-blue-200 rounded-xl transition-colors border-t border-blue-200 pt-4">
                        <div class="flex items-center">
                            <span class="text-xl">⚙️</span> 
                            <span class="ml-3 font-bold uppercase text-[10px] tracking-[0.2em]">Configuración</span>
                        </div>
                        <svg class="w-4 h-4 transition-transform duration-200" 
                            :class="openMenus.config ? 'rotate-180' : ''" 
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>

                    <ul x-show="openMenus.config" 
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-2"
                        class="mt-1 space-y-1">
                        <li @click="showConfigModal = true" 
                            class="ml-10 p-2 text-slate-600 hover:text-c2d-blue rounded-lg cursor-pointer transition-all text-sm font-medium">
                            Conectar a C2D OnCloud
                        </li>
                        <li @click="activeModule = 'realtime'"
                            class="ml-10 p-2 text-slate-600 hover:text-c2d-blue rounded-lg cursor-pointer transition-all text-sm font-medium">
                            Sincronización real-time
                        </li>
                    </ul>
                </li>

            </ul>
        </nav>
    </aside>

    <main class="flex-1 flex flex-col relative z-10 min-w-0 bg-white">
        <header class="h-16 bg-white border-b border-slate-100 flex items-center justify-between px-8 shrink-0">
            <div class="font-nunito text-c2d-dark-blue">
                Hola, <span class="text-slate-900 font-bold">{{ Auth::user()->first_name ?? 'Administrator' }}</span>
                <span class="ml-2 text-[10px] bg-c2d-blue text-white px-2 py-0.5 rounded-full font-bold uppercase tracking-widest">{{ Auth::user()->role }}</span>
            </div>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-red-500 font-bold text-xs uppercase tracking-widest hover:text-red-700 transition-colors">Cerrar Sesión</button>
            </form>
        </header>

        <div x-show="activeModule === 'chats'" class="iframe-container w-full h-full bg-slate-50">
            <iframe src="https://web.chat2desk.com.mx/?auth_key={{ Auth::user()->auth_key }}" frameborder="0" class="w-full h-full" allow="geolocation; microphone; camera"></iframe>
        </div>

        <div x-show="activeModule === 'users'" x-cloak class="p-10 overflow-y-auto bg-white flex-1">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-10 border-b border-slate-50 pb-6">
                    <div>
                        <h1 class="text-3xl text-c2d-dark-blue font-nunito">Análisis de Usuarios</h1>
                        <p class="text-slate-400 text-sm mt-1">Distribución de equipo por roles en la plataforma</p>
                    </div>
                    <button @click="activeModule = 'chats'" class="bg-c2d-dark-blue text-white px-6 py-2 rounded-xl text-xs font-bold uppercase tracking-widest hover:bg-c2d-blue transition-all shadow-lg">
                        ← Volver a los Chats
                    </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                    <div class="lg:col-span-7 bg-slate-50 p-10 rounded-[2rem] border border-blue-50">
                        <h2 class="text-center font-nunito text-c2d-dark-blue mb-8 uppercase text-xs tracking-[0.3em] font-bold">Composición del Equipo</h2>
                        <div class="h-80 relative"><canvas id="rolesChartBranded"></canvas></div>
                    </div>
                    <div class="lg:col-span-5 flex flex-col gap-4">
                        <h2 class="font-nunito text-c2d-dark-blue mb-2 uppercase text-xs tracking-[0.3em] font-bold">Resumen Numérico</h2>
                        <template x-for="stat in stats" :key="stat.role">
                            <div class="flex items-center justify-between p-6 bg-white border border-blue-50 rounded-2xl shadow-sm hover:shadow-md transition-all group">
                                <div class="flex items-center">
                                    <div class="w-2 h-2 rounded-full mr-4" :class="stat.role === 'supervisor' ? 'bg-[#205B9B]' : (stat.role === 'operator' ? 'bg-[#8FABD9]' : 'bg-[#0A3656]')"></div>
                                    <span class="capitalize font-bold text-slate-700 group-hover:text-c2d-blue transition-colors font-nunito" x-text="stat.role"></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-2xl font-bold text-c2d-dark-blue" x-text="stat.total"></span>
                                    <span class="text-[9px] text-slate-400 block uppercase font-bold tracking-tighter">Usuarios</span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="activeModule === 'realtime'" x-cloak class="p-10 overflow-y-auto bg-white flex-1">
            <div class="max-w-3xl mx-auto">
                <div class="border-b border-slate-100 pb-6 mb-8">
                    <h1 class="text-3xl text-c2d-dark-blue font-nunito">Sincronización real-time</h1>
                    <p class="text-slate-400 text-sm mt-1">Recibe mensajes nuevos de Chat2Desk mediante webhooks.</p>
                </div>

                <div class="rounded-3xl border border-blue-50 bg-slate-50 p-8">
                    <div class="flex items-center justify-between gap-6">
                        <div>
                            <h2 class="font-nunito text-xl text-c2d-dark-blue">Activar en API_C2D</h2>
                            <p class="text-sm text-slate-500 mt-2">
                                Esto habilita el modo real-time dentro de nuestra plataforma.
                                La cuenta todavía debe apuntarse manualmente en Chat2Desk.
                            </p>
                        </div>
                        <button type="button" @click="realtimeEnabled = !realtimeEnabled; toggleRealtime()"
                                :disabled="realtimeSaving"
                                :class="realtimeEnabled ? 'bg-c2d-blue' : 'bg-slate-300'"
                                class="relative inline-flex h-8 w-14 shrink-0 rounded-full transition-colors disabled:opacity-50">
                            <span :class="realtimeEnabled ? 'translate-x-7' : 'translate-x-1'"
                                  class="mt-1 inline-block h-6 w-6 rounded-full bg-white shadow transition-transform"></span>
                        </button>
                    </div>
                    <p x-show="realtimeMessage" x-text="realtimeMessage" class="mt-6 rounded-xl bg-white p-4 text-xs text-slate-600"></p>
                </div>

                <div class="mt-6 rounded-3xl border border-amber-100 bg-amber-50 p-8">
                    <h2 class="font-nunito text-lg text-amber-900">Configuración pendiente en Chat2Desk</h2>
                    <p class="mt-2 text-sm text-amber-800">Registra esta URL en cada cuenta de Chat2Desk:</p>
                    <code class="mt-4 block break-all rounded-xl bg-white p-4 text-sm text-slate-700">{{ $webhook_url }}</code>
                    <p class="mt-4 text-xs text-amber-800">Eventos recomendados: inbox, outbox, imported_message.</p>
                </div>
            </div>
        </div>
    </main>

    <div x-show="showConfigModal" x-cloak class="relative z-50">
        <div class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm" x-show="showConfigModal" x-transition.opacity></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto flex items-center justify-center p-4">
            <div @click.away="!isSyncing && (showConfigModal = false)" class="relative transform overflow-hidden rounded-[2.5rem] bg-white text-center shadow-2xl sm:w-full sm:max-w-md p-10">
                
                <template x-if="companyStatus === 'active'">
                    <div>
                        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-blue-50 mb-8">
                            <svg class="h-10 w-10 text-c2d-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <h3 class="text-2xl font-nunito text-c2d-dark-blue">¡Todo en orden!</h3>
                        <p class="text-slate-500 mt-2 text-sm">C2D OnCloud Sincronizado exitosamente.</p>
                        <button @click="showConfigModal = false" class="mt-10 w-full bg-c2d-dark-blue text-white py-4 rounded-2xl font-bold hover:bg-c2d-blue transition-all shadow-lg uppercase text-xs tracking-widest">OK</button>
                    </div>
                </template>

                <template x-if="companyStatus !== 'active'">
                    <div class="text-left">
                        <h3 class="text-2xl font-nunito text-c2d-dark-blue mb-2">Conectar C2D</h3>
                        <p class="text-sm text-slate-400 mb-8">Ingresa tu API Token para sincronizar la plataforma.</p>
                        <form @submit.prevent="startSync()">
                            <div x-show="!isSyncing">
                                <label class="text-[10px] font-bold uppercase text-slate-400 tracking-widest">Admin API Token</label>
                                <input type="password" id="api_token_input" class="w-full rounded-2xl border-slate-100 mt-2 bg-slate-50 focus:ring-c2d-blue py-3 px-4">
                            </div>
                            <div x-show="isSyncing" class="mt-4">
                                <div class="flex justify-between text-[10px] text-c2d-blue mb-2 font-bold uppercase"><span x-text="syncMessage"></span><span x-text="progress + '%'"></span></div>
                                <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden"><div class="bg-c2d-blue h-full transition-all duration-500" :style="'width: ' + progress + '%'"></div></div>
                            </div>
                            <div class="mt-10 flex gap-4">
                                <button type="button" @click="showConfigModal = false" class="flex-1 py-3 text-slate-400 font-bold text-xs uppercase tracking-widest">Cerrar</button>
                                <button type="submit" :disabled="isSyncing" class="flex-1 bg-c2d-dark-blue text-white py-4 rounded-2xl font-bold text-xs uppercase tracking-widest shadow-lg">Conectar</button>
                            </div>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>
</body>
</html>
