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
        .dashboard-scroll { height: calc(100vh - 4rem); overflow-y: scroll; overscroll-behavior: contain; }
        .dashboard-scroll::-webkit-scrollbar { width: 12px; }
        .dashboard-scroll::-webkit-scrollbar-track { background: #f1f5f9; }
        .dashboard-scroll::-webkit-scrollbar-thumb { background: #205B9B; border-radius: 999px; border: 3px solid #f1f5f9; }
    </style>
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden"
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
         extractionYear: new Date().getFullYear(),
         extractionMonth: new Date().getMonth() + 1,
         extractionSaving: false,
         extractionMessage: '',
         syncPeriods: [],
         selectedMessages: [],
         selectedPeriod: '',
         messagesLoading: false,
         conversations: [],
         conversationPage: 1,
         conversationTotal: 0,
         conversationPeriod: '',
         conversationLoading: false,
         selectedConversation: null,
         copiedId: '',
         geminiConfigured: false,
         geminiKey: '',
         promptList: [],
         selectedPromptKey: '',
         selectedPromptId: '',
         promptName: '',
         promptText: '',
         promptMessage: '',
         analysisDialogId: '',
         analysisRequestId: '',
         analysisYear: new Date().getFullYear(),
         analysisMonth: new Date().getMonth() + 1,
         analysisResult: '',
         analysisTokens: null,
         analysisJobId: null,
         analysisSaving: false,
         analysisMaxConversations: 1,
         messageFilters: { dialog_id: '', request_id: '', client_id: '', message_type: '', date_from: '', date_to: '' },

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
                 const responseType = response.headers.get('content-type') || '';
                 if (!responseType.includes('application/json')) {
                     throw new Error('El servidor devolvió una página HTML (HTTP ' + response.status + '). Revisa tu sesión e inténtalo de nuevo.');
                 }
                 let result = await response.json();
                 if (!response.ok) throw new Error(result.message || 'No se pudo conectar con Chat2Desk.');
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
         ,

         async startExtraction() {
             this.extractionSaving = true;
             this.extractionMessage = 'Iniciando extracción...';
             try {
                 const response = await fetch('{{ route('config.extract') }}', {
                     method: 'POST',
                     headers: {
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': '{{ csrf_token() }}'
                     },
                     body: JSON.stringify({
                         year: Number(this.extractionYear),
                         month: Number(this.extractionMonth),
                         exclude_autoreply: true
                     })
                 });
                 const result = await response.json();
                 if (!response.ok) throw new Error(result.detail || result.message || 'No se pudo iniciar.');
                 this.extractionMessage = 'Extracción iniciada. Revisa el estado en unos segundos.';
                 await this.loadSyncStatus();
             } catch (error) {
                 this.extractionMessage = error.message;
             } finally {
                 this.extractionSaving = false;
             }
         },

         async loadSyncStatus() {
             const response = await fetch('{{ route('config.sync.status') }}', { headers: { 'Accept': 'application/json' } });
             if (response.ok) {
                 this.syncPeriods = (await response.json()).periods || [];
             }
         },

         async viewMessages(period) {
             this.messagesLoading = true;
             this.selectedPeriod = period.year + '-' + String(period.month).padStart(2, '0');
             try {
                 const params = new URLSearchParams({ year: period.year, month: period.month, ...this.messageFilters });
                 for (const [key, value] of [...params.entries()]) if (!value) params.delete(key);
                 const response = await fetch('{{ route('config.messages') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                 const result = await response.json();
                 if (!response.ok) throw new Error(result.detail || result.message || 'No se pudieron consultar los mensajes.');
                 this.selectedMessages = result.messages || [];
                 this.$nextTick(() => document.getElementById('messages-results')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
             } catch (error) {
                 this.extractionMessage = error.message;
             } finally {
                 this.messagesLoading = false;
             }
         }
         ,

         async loadConversations(period, page = 1) {
             this.conversationLoading = true;
             this.conversationPeriod = period.year + '-' + String(period.month).padStart(2, '0');
             this.selectedPeriod = this.conversationPeriod;
             this.selectedMessages = [];
             this.selectedConversation = null;
             this.messageFilters = { dialog_id: '', request_id: '', client_id: '', message_type: '', date_from: '', date_to: '' };
             this.conversationPage = page;
             try {
                 const response = await fetch('{{ route('config.conversations') }}?year=' + period.year + '&month=' + period.month + '&page=' + page, { headers: { 'Accept': 'application/json' } });
                 const result = await response.json();
                 if (!response.ok) throw new Error(result.detail || result.message || 'No se pudieron consultar las conversaciones.');
                 this.conversations = result.conversations || [];
                 this.conversationTotal = result.total || 0;
                 this.selectedConversation = null;
                 this.$nextTick(() => document.getElementById('conversation-results')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
             } catch (error) {
                 this.extractionMessage = error.message;
             } finally {
                 this.conversationLoading = false;
             }
         },

         async viewConversation(conversation) {
             const [year, month] = this.conversationPeriod.split('-');
             const params = new URLSearchParams({ year, month, request_id: conversation.request_id });
             const response = await fetch('{{ url('/config/conversations') }}/' + encodeURIComponent(conversation.dialog_id) + '?' + params.toString(), { headers: { 'Accept': 'application/json' } });
             const result = await response.json();
             if (!response.ok) throw new Error(result.detail || result.message || 'No se pudo consultar la conversación.');
             this.selectedConversation = result;
             this.$nextTick(() => document.getElementById('conversation-detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
         },

         async copyId(value, label) {
             try {
                 await navigator.clipboard.writeText(String(value));
             } catch (error) {
                 const input = document.createElement('textarea');
                 input.value = String(value);
                 input.style.position = 'fixed';
                 input.style.opacity = '0';
                 document.body.appendChild(input);
                 input.select();
                 document.execCommand('copy');
                 input.remove();
             }
             this.copiedId = label;
             setTimeout(() => this.copiedId = '', 1500);
         },

         async loadAnalysis() {
             const [status, prompts] = await Promise.all([
                 fetch('{{ route('config.gemini.status') }}'),
                 fetch('{{ route('config.prompts') }}')
             ]);
             if (status.ok) this.geminiConfigured = (await status.json()).configured;
             if (prompts.ok) this.promptList = (await prompts.json()).prompts || [];
         },

         selectPrompt() {
             const prompt = this.promptList.find(item => item.key === this.selectedPromptKey);
             if (prompt) {
                 this.promptName = prompt.name;
                 this.promptText = prompt.prompt_text;
                 this.selectedPromptId = prompt.source === 'client' ? prompt.id : '';
             }
         },

         async saveGeminiKey() {
             const response = await fetch('{{ route('config.gemini.key') }}', {
                 method: 'PUT',
                 headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                 body: JSON.stringify({ api_key: this.geminiKey })
             });
             const result = await response.json();
             this.promptMessage = result.message || 'No se pudo guardar la clave.';
             if (response.ok) { this.geminiConfigured = true; this.geminiKey = ''; }
         },

         async savePrompt() {
             const response = await fetch('{{ route('config.prompts.store') }}', {
                 method: 'POST',
                 headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                 body: JSON.stringify({ name: this.promptName, prompt_text: this.promptText })
             });
             const result = await response.json();
             this.promptMessage = result.message || (response.ok ? 'Prompt guardado.' : 'No se pudo guardar el prompt.');
             if (response.ok) { this.promptList.push({ ...result.prompt, key: 'client-' + result.prompt.id, source: 'client' }); this.selectedPromptId = result.prompt.id; this.selectedPromptKey = 'client-' + result.prompt.id; }
         },

         async analyzeConversation() {
             this.analysisSaving = true;
             this.analysisResult = '';
             this.analysisTokens = null;
             this.analysisJobId = null;
             try {
                 const response = await fetch('{{ route('config.analyze.conversation') }}', {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                     body: JSON.stringify({
                         dialog_id: this.analysisDialogId,
                         request_id: this.analysisRequestId || null,
                         year: Number(this.analysisYear),
                         month: Number(this.analysisMonth),
                         client_prompt_id: this.selectedPromptId || null,
                         prompt_text: this.promptText
                     })
                 });
                 const result = await response.json();
                 if (!response.ok) throw new Error(result.detail || result.message || 'No se pudo ejecutar el análisis.');
                 this.analysisResult = result.result;
                 this.analysisTokens = result.tokens;
                 this.analysisJobId = result.job_id;
             } catch (error) {
                 this.analysisTokens = { input: 0, output: 0, total: 0 };
                 this.promptMessage = error.message;
             } finally {
                 this.analysisSaving = false;
             }
         },

         async analyzePeriod() {
             this.analysisSaving = true;
             this.analysisResult = '';
             this.analysisTokens = null;
             this.analysisJobId = null;
             try {
                 const response = await fetch('{{ route('config.analyze.period') }}', {
                     method: 'POST',
                     headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                     body: JSON.stringify({
                         year: Number(this.analysisYear),
                         month: Number(this.analysisMonth),
                         client_prompt_id: this.selectedPromptId || null,
                         prompt_text: this.promptText,
                         max_conversations: Number(this.analysisMaxConversations)
                     })
                 });
                 const result = await response.json();
                 if (!response.ok) throw new Error(result.detail || result.message || 'No se pudo analizar el periodo.');
                 this.analysisResult = 'Conversaciones procesadas: ' + result.processed + '\n\n' + result.results.map(item => item.result).join('\n\n---\n\n');
                 this.analysisTokens = result.results.reduce((total, item) => ({
                     input: total.input + (item.tokens?.input || 0),
                     output: total.output + (item.tokens?.output || 0),
                     total: total.total + (item.tokens?.total || 0)
                 }), { input: 0, output: 0, total: 0 });
             } catch (error) {
                 this.analysisTokens = { input: 0, output: 0, total: 0 };
                 this.promptMessage = error.message;
             } finally {
                 this.analysisSaving = false;
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
                        <li @click="activeModule = 'extraction'; loadSyncStatus()"
                            class="ml-10 p-2 text-slate-600 hover:text-c2d-blue rounded-lg cursor-pointer transition-all text-sm font-medium">
                            Extraer conversaciones
                        </li>
                        <li @click="activeModule = 'analysis'; loadAnalysis()"
                            class="ml-10 p-2 text-slate-600 hover:text-c2d-blue rounded-lg cursor-pointer transition-all text-sm font-medium">
                            Análisis IA
                        </li>
                    </ul>
                </li>

            </ul>
        </nav>
    </aside>

    <main class="flex h-screen min-h-0 flex-1 flex-col relative z-10 min-w-0 overflow-hidden bg-white">
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

        <div x-show="activeModule === 'extraction'" x-cloak class="dashboard-scroll min-h-0 flex-1 p-10 bg-white" style="scrollbar-width: auto;">
            <div class="max-w-4xl mx-auto">
                <div class="border-b border-slate-100 pb-6 mb-8">
                    <h1 class="text-3xl text-c2d-dark-blue font-nunito">Extraer conversaciones</h1>
                    <p class="text-slate-400 text-sm mt-1">Selecciona el periodo que quieres guardar desde Chat2Desk.</p>
                </div>
                <div class="rounded-3xl border border-blue-50 bg-slate-50 p-8">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <label class="text-xs font-bold uppercase tracking-widest text-slate-500">
                            Año
                            <input type="number" min="2020" max="2030" x-model="extractionYear" class="mt-2 w-full rounded-xl border-slate-200 bg-white px-4 py-3">
                        </label>
                        <label class="text-xs font-bold uppercase tracking-widest text-slate-500">
                            Mes
                            <select x-model="extractionMonth" class="mt-2 w-full rounded-xl border-slate-200 bg-white px-4 py-3">
                                @foreach(range(1, 12) as $month)
                                    <option value="{{ $month }}">{{ DateTime::createFromFormat('!m', $month)->format('F') }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <button type="button" @click="startExtraction()" :disabled="extractionSaving"
                            class="mt-6 rounded-xl bg-c2d-dark-blue px-6 py-3 text-xs font-bold uppercase tracking-widest text-white hover:bg-c2d-blue disabled:opacity-50">
                        <span x-text="extractionSaving ? 'Procesando...' : 'Iniciar extracción'"></span>
                    </button>
                    <p x-show="extractionMessage" x-text="extractionMessage" class="mt-4 rounded-xl bg-white p-4 text-sm text-slate-600"></p>
                </div>

                <div class="mt-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-nunito text-xl text-c2d-dark-blue">Estado de sincronización</h2>
                        <button @click="loadSyncStatus()" class="text-xs font-bold uppercase tracking-widest text-c2d-blue">Actualizar</button>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-slate-100">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-widest text-slate-500">
                                <tr><th class="px-5 py-4">Periodo</th><th class="px-5 py-4">Estado</th><th class="px-5 py-4">Conversaciones</th><th class="px-5 py-4">Mensajes</th><th class="px-5 py-4">Consulta</th></tr>
                            </thead>
                            <tbody>
                                <template x-for="period in syncPeriods" :key="period.id">
                                    <tr class="border-t border-slate-100"><td class="px-5 py-4" x-text="period.year + '-' + String(period.month).padStart(2, '0')"></td><td class="px-5 py-4 capitalize" x-text="period.status"></td><td class="px-5 py-4" x-text="period.total_dialogs"></td><td class="px-5 py-4" x-text="period.total_messages"></td><td class="px-5 py-4"><button @click="loadConversations(period)" class="text-xs font-bold uppercase tracking-widest text-c2d-blue" x-text="conversationLoading && conversationPeriod === (period.year + '-' + String(period.month).padStart(2, '0')) ? 'Cargando...' : 'Ver conversaciones'"></button></td></tr>
                                </template>
                                <tr x-show="syncPeriods.length === 0"><td colspan="5" class="px-5 py-8 text-center text-slate-400">Todavía no hay periodos registrados.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="conversation-results" x-show="conversationPeriod" class="mt-8 rounded-3xl border border-blue-50 bg-slate-50 p-6">
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="font-nunito text-xl text-c2d-dark-blue">Conversaciones <span x-text="conversationPeriod"></span></h2>
                        <span class="text-xs text-slate-400" x-text="conversationTotal + ' conversaciones'"></span>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4">
                        <template x-for="conversation in conversations" :key="conversation.dialog_id + '-' + conversation.request_id">
                            <article @click="viewConversation(conversation)" tabindex="0" @keydown.enter="viewConversation(conversation)" class="w-full cursor-pointer rounded-2xl border border-slate-100 bg-white p-5 text-left shadow-sm transition hover:border-c2d-blue hover:shadow-md">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="font-bold text-c2d-dark-blue">Dialog <span class="select-text" x-text="conversation.dialog_id"></span></span>
                                        <button type="button" @click.stop="copyId(conversation.dialog_id, 'dialog-' + conversation.dialog_id)" class="rounded-lg border border-blue-100 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-c2d-blue hover:bg-blue-50" x-text="copiedId === ('dialog-' + conversation.dialog_id) ? 'Copiado' : 'Copiar ID'"></button>
                                    </div>
                                    <span class="rounded-full bg-blue-50 px-3 py-1 text-xs text-c2d-blue" x-text="conversation.total_messages + ' mensajes'"></span>
                                </div>
                                <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-slate-500 sm:grid-cols-3">
                                    <span class="flex items-center gap-2">Request: <span class="select-text" x-text="conversation.request_id"></span><button type="button" @click.stop="copyId(conversation.request_id, 'request-' + conversation.request_id)" class="rounded border border-slate-200 px-1.5 py-0.5 text-[9px] font-bold uppercase text-slate-500 hover:bg-slate-50" x-text="copiedId === ('request-' + conversation.request_id) ? 'OK' : 'Copiar'"></button></span>
                                    <span x-text="'Cliente: ' + conversation.client_id"></span>
                                    <span x-text="conversation.first_message + ' → ' + conversation.last_message"></span>
                                </div>
                            </article>
                        </template>
                        <p x-show="conversations.length === 0 && !conversationLoading" class="py-6 text-center text-sm text-slate-400">No hay conversaciones para este periodo.</p>
                    </div>
                    <div class="mt-5 flex items-center justify-between" x-show="conversationTotal > 20">
                        <button @click="loadConversations({ year: Number(conversationPeriod.slice(0, 4)), month: Number(conversationPeriod.slice(5, 7)) }, conversationPage - 1)" :disabled="conversationPage <= 1" class="rounded-lg px-3 py-2 text-xs font-bold text-c2d-blue disabled:opacity-30">Anterior</button>
                        <span class="text-xs text-slate-400" x-text="'Página ' + conversationPage"></span>
                        <button @click="loadConversations({ year: Number(conversationPeriod.slice(0, 4)), month: Number(conversationPeriod.slice(5, 7)) }, conversationPage + 1)" :disabled="conversationPage * 20 >= conversationTotal" class="rounded-lg px-3 py-2 text-xs font-bold text-c2d-blue disabled:opacity-30">Siguiente</button>
                    </div>
                </div>

                <div id="conversation-detail" x-show="selectedConversation" class="mt-8 rounded-3xl border border-blue-50 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-4">
                        <div>
                            <h2 class="font-nunito text-xl text-c2d-dark-blue">Timeline de conversación</h2>
                            <p class="text-xs text-slate-400" x-text="selectedConversation ? 'Dialog ' + selectedConversation.dialog_id + ' · Request ' + selectedConversation.request_id : ''"></p>
                        </div>
                        <button @click="selectedConversation = null" class="text-xs font-bold uppercase tracking-widest text-c2d-blue">Cerrar</button>
                    </div>
                    <div class="mt-5 space-y-4">
                        <template x-for="message in (selectedConversation ? selectedConversation.messages : [])" :key="message.id">
                            <div class="flex" :class="message.tipo === 'from_client' ? 'justify-start' : 'justify-end'">
                                <article class="max-w-2xl rounded-2xl p-4" :class="message.tipo === 'from_client' ? 'bg-slate-100' : 'bg-blue-50'">
                                    <div class="flex justify-between gap-5 text-[10px] font-bold uppercase tracking-widest text-slate-400"><span x-text="message.tipo"></span><span x-text="message.fecha_creacion"></span></div>
                                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700" x-text="message.texto || '(sin texto)' "></p>
                                </article>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="selectedPeriod" class="mt-8 rounded-3xl border border-blue-50 bg-slate-50 p-6">
                    <h2 class="font-nunito text-lg text-c2d-dark-blue">Filtros de mensajes</h2>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-6">
                        <input x-model="messageFilters.dialog_id" placeholder="Dialog ID" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-sm">
                        <input x-model="messageFilters.request_id" placeholder="Request ID" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-sm">
                        <input x-model="messageFilters.client_id" placeholder="Client ID" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-sm">
                        <select x-model="messageFilters.message_type" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-sm">
                            <option value="">Todos los tipos</option>
                            <option value="from_client">from_client</option>
                            <option value="to_client">to_client</option>
                            <option value="autoreply">autoreply</option>
                        </select>
                        <input type="date" x-model="messageFilters.date_from" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-sm">
                        <input type="date" x-model="messageFilters.date_to" class="rounded-xl border-slate-200 bg-white px-3 py-2 text-sm">
                    </div>
                    <button @click="viewMessages({ year: Number(selectedPeriod.slice(0, 4)), month: Number(selectedPeriod.slice(5, 7)) })" class="mt-4 rounded-xl bg-c2d-blue px-5 py-2 text-xs font-bold uppercase tracking-widest text-white">Aplicar filtros</button>
                </div>

                <div id="messages-results" x-show="selectedPeriod" class="mt-8">
                    <h2 class="font-nunito text-xl text-c2d-dark-blue mb-4">Mensajes del periodo <span x-text="selectedPeriod"></span></h2>
                    <div class="space-y-3">
                        <template x-for="message in selectedMessages" :key="message.id">
                            <article class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                                <div class="flex justify-between gap-4 text-xs text-slate-400"><span x-text="message.tipo"></span><span x-text="message.fecha_creacion"></span></div>
                                <p class="mt-3 text-sm text-slate-700 whitespace-pre-wrap" x-text="message.texto || '(sin texto)'"></p>
                                <p class="mt-3 text-[10px] uppercase tracking-widest text-slate-400" x-text="'Request ' + message.request_id + ' · Dialog ' + message.dialog_id"></p>
                            </article>
                        </template>
                        <p x-show="selectedPeriod && selectedMessages.length === 0 && !messagesLoading" class="text-sm text-slate-400">No hay mensajes guardados para este periodo.</p>
                    </div>
                </div>
            </div>
        </div>

        <div x-show="activeModule === 'analysis'" x-cloak class="dashboard-scroll min-h-0 flex-1 p-10 bg-white" style="scrollbar-width: auto;">
            <div class="mx-auto max-w-4xl space-y-8">
                <div class="border-b border-slate-100 pb-6">
                    <h1 class="text-3xl font-nunito text-c2d-dark-blue">Análisis IA</h1>
                    <p class="mt-1 text-sm text-slate-400">El análisis se ejecuta únicamente cuando tú lo solicitas.</p>
                </div>

                <div class="rounded-3xl border border-amber-100 bg-amber-50 p-6">
                    <h2 class="font-nunito text-lg text-amber-900">Configuración de Gemini</h2>
                    <p class="mt-2 text-sm text-amber-800">La API key se guarda cifrada en el servidor y nunca se muestra después.</p>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <input type="password" x-model="geminiKey" placeholder="API key de Gemini" class="flex-1 rounded-xl border-amber-200 bg-white px-4 py-3 text-sm">
                        <button @click="saveGeminiKey()" class="rounded-xl bg-amber-700 px-5 py-3 text-xs font-bold uppercase tracking-widest text-white">Guardar clave</button>
                    </div>
                    <p class="mt-3 text-xs" :class="geminiConfigured ? 'text-green-700' : 'text-amber-800'" x-text="geminiConfigured ? 'API key configurada' : 'API key pendiente' "></p>
                </div>

                <div class="rounded-3xl border border-blue-50 bg-slate-50 p-6">
                    <h2 class="font-nunito text-lg text-c2d-dark-blue">Prompt</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <select x-model="selectedPromptKey" @change="selectPrompt()" class="rounded-xl border-slate-200 bg-white px-4 py-3 text-sm">
                            <option value="">Nuevo prompt personalizado</option>
                            <template x-for="prompt in promptList" :key="prompt.key"><option :value="prompt.key" x-text="prompt.name"></option></template>
                        </select>
                        <input x-model="promptName" placeholder="Nombre para guardar el prompt" class="rounded-xl border-slate-200 bg-white px-4 py-3 text-sm">
                    </div>
                    <textarea x-model="promptText" rows="7" placeholder="Escribe las instrucciones para Gemini..." class="mt-4 w-full rounded-xl border-slate-200 bg-white px-4 py-3 text-sm"></textarea>
                    <p class="mt-2 text-xs text-slate-400">Las plantillas base usan variables como <code>@{{industry}}</code> y <code>@{{company_context}}</code>. Reemplázalas con la información de cada empresa antes de guardarlas.</p>
                    <button @click="savePrompt()" class="mt-4 rounded-xl bg-c2d-blue px-5 py-3 text-xs font-bold uppercase tracking-widest text-white">Guardar prompt</button>
                </div>

                <div class="rounded-3xl border border-blue-50 bg-white p-6 shadow-sm">
                    <h2 class="font-nunito text-lg text-c2d-dark-blue">Analizar un periodo mensual</h2>
                    <p class="mt-2 text-sm text-slate-500">No necesitas ingresar un Dialog ID. El sistema tomará las conversaciones extraídas del mes seleccionado.</p>
                    <div class="mt-4 flex flex-wrap items-end gap-4">
                        <label class="text-xs font-bold uppercase tracking-widest text-slate-500">Año<input type="number" x-model="analysisYear" min="2020" max="2030" class="mt-2 block w-32 rounded-xl border-slate-200 px-4 py-3 text-sm"></label>
                        <label class="text-xs font-bold uppercase tracking-widest text-slate-500">Mes<input type="number" x-model="analysisMonth" min="1" max="12" class="mt-2 block w-24 rounded-xl border-slate-200 px-4 py-3 text-sm"></label>
                        <label class="text-xs font-bold uppercase tracking-widest text-slate-500">Máximo de conversaciones<input type="number" min="1" max="100" x-model="analysisMaxConversations" class="mt-2 block w-40 rounded-xl border-slate-200 px-4 py-3 text-sm"></label>
                        <button @click="analyzePeriod()" :disabled="analysisSaving || !geminiConfigured" class="rounded-xl bg-c2d-dark-blue px-5 py-3 text-xs font-bold uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-40">Analizar periodo</button>
                    </div>
                    <p class="mt-3 text-xs text-slate-400">Comienza con 1 conversación para controlar el consumo de tokens.</p>
                </div>

                <div class="rounded-3xl border border-blue-50 bg-white p-6 shadow-sm">
                    <h2 class="font-nunito text-lg text-c2d-dark-blue">Analizar una conversación (opcional)</h2>
                    <p class="mt-2 text-sm text-slate-500">Usa esta opción solo para revisar un Dialog o Request específico.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <input x-model="analysisDialogId" placeholder="Dialog ID" class="rounded-xl border-slate-200 px-4 py-3 text-sm">
                        <input x-model="analysisRequestId" placeholder="Request ID opcional" class="rounded-xl border-slate-200 px-4 py-3 text-sm">
                        <input type="number" x-model="analysisYear" min="2020" max="2030" class="rounded-xl border-slate-200 px-4 py-3 text-sm">
                        <input type="number" x-model="analysisMonth" min="1" max="12" class="rounded-xl border-slate-200 px-4 py-3 text-sm">
                    </div>
                    <button @click="analyzeConversation()" :disabled="analysisSaving || !geminiConfigured" class="mt-5 rounded-xl bg-c2d-dark-blue px-5 py-3 text-xs font-bold uppercase tracking-widest text-white disabled:cursor-not-allowed disabled:opacity-40">
                        <span x-text="analysisSaving ? 'Analizando...' : 'Analizar conversación'"></span>
                    </button>
                    <p x-show="promptMessage" x-text="promptMessage" class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600"></p>
                </div>

                <div x-show="analysisResult || analysisTokens" class="rounded-3xl border border-green-100 bg-green-50 p-6">
                    <h2 class="font-nunito text-lg text-green-900">Resultado</h2>
                    <p class="mt-4 whitespace-pre-wrap text-sm text-green-950" x-text="analysisResult"></p>
                    <div x-show="analysisTokens" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-white p-4"><span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400">Entrada</span><strong class="mt-1 block text-xl text-c2d-dark-blue" x-text="analysisTokens ? analysisTokens.input : 0"></strong></div>
                        <div class="rounded-xl bg-white p-4"><span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400">Salida</span><strong class="mt-1 block text-xl text-c2d-dark-blue" x-text="analysisTokens ? analysisTokens.output : 0"></strong></div>
                        <div class="rounded-xl bg-white p-4"><span class="block text-[10px] font-bold uppercase tracking-widest text-slate-400">Total</span><strong class="mt-1 block text-xl text-c2d-dark-blue" x-text="analysisTokens ? analysisTokens.total : 0"></strong></div>
                    </div>
                    <p x-show="analysisJobId" class="mt-4 text-xs text-green-700" x-text="'Job de análisis: ' + analysisJobId"></p>
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
