<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Operadores | C2D OnCloud</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-100 p-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">Análisis de Operadores</h1>
                <p class="text-slate-500">Distribución de equipo por roles en Chat2Desk</p>
            </div>
            <a href="{{ route('dashboard') }}" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm hover:bg-slate-700 transition-colors">
                ← Volver al Dashboard
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-lg font-semibold mb-4 text-slate-700">Composición del Equipo</h2>
                <canvas id="rolesChart" width="400" height="400"></canvas>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md">
                <h2 class="text-lg font-semibold mb-4 text-slate-700">Resumen Numérico</h2>
                <div class="space-y-4">
                    @foreach($stats as $stat)
                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
                        <span class="capitalize font-medium text-slate-600">{{ $stat->role }}</span>
                        <span class="bg-blue-100 text-blue-800 py-1 px-3 rounded-full text-sm font-bold">
                            {{ $stat->total }}
                        </span>
                    </div>
                    @endforeach
                </div>
                <div class="mt-6 pt-6 border-t border-slate-100">
                    <p class="text-xs text-slate-400 italic">Sincronizado automáticamente desde Chat2Desk API</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const ctx = document.getElementById('rolesChart').getContext('2d');
        const data = {
            labels: {!! json_encode($stats->pluck('role')) !!},
            datasets: [{
                label: 'Cantidad de Usuarios',
                data: {!! json_encode($stats->pluck('total')) !!},
                backgroundColor: [
                    '#3b82f6', // blue-500
                    '#10b981', // emerald-500
                    '#f59e0b', // amber-500
                    '#8b5cf6', // violet-500
                    '#f43f5e'  // rose-500
                ],
                hoverOffset: 4
            }]
        };

        new Chart(ctx, {
            type: 'doughnut',
            data: data,
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>