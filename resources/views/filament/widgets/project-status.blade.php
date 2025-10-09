<div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
    <h2 class="text-xl font-bold mb-4">Project Status Overview</h2>
    <div>
        <canvas id="projectStatusChart" height="120"></canvas>
    </div>
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($statusData as $project)
            <div
                class="p-4 rounded border flex flex-col gap-2 {{ $project['status'] === 'ontrack' ? 'border-green-400 bg-green-50' : ($project['status'] === 'risk' ? 'border-yellow-400 bg-yellow-50' : 'border-red-400 bg-red-50') }}">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-block w-3 h-3 rounded-full {{ $project['status'] === 'ontrack' ? 'bg-green-500' : ($project['status'] === 'risk' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                    <span class="font-semibold">{{ $project['name'] }}</span>
                </div>
                <div class="text-xs text-gray-600">Actual: {{ $project['actual'] }}% | Planned:
                    {{ $project['planned'] }}% | Deviation: {{ $project['deviation'] }}%</div>
                <div class="text-xs">Status: <span class="font-bold capitalize">{{ $project['status'] }}</span></div>
            </div>
        @endforeach
    </div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('projectStatusChart');
            if (!ctx) return;
            const data = @json($statusData);
            const labels = data.map(p => p.name);
            const actual = data.map(p => p.actual);
            const planned = data.map(p => p.planned);
            const deviation = data.map(p => p.deviation);
            if (window.projectStatusChart) window.projectStatusChart.destroy();
            window.projectStatusChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                            label: 'Actual',
                            data: actual,
                            backgroundColor: '#3B82F6'
                        },
                        {
                            label: 'Planned',
                            data: planned,
                            backgroundColor: '#10B981'
                        },
                        {
                            label: 'Deviation',
                            data: deviation,
                            backgroundColor: '#F59E0B'
                        },
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: 'Project Progress Comparison'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Progress (%)'
                            }
                        }
                    }
                }
            });
        });
    </script>
</div>
