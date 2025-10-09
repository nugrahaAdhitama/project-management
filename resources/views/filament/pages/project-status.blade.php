<x-filament-panels::page>
    <style>
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        .status-indicator.ontrack {
            background-color: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.3);
        }

        .status-indicator.risk {
            background-color: #f59e0b;
            box-shadow: 0 0 8px rgba(245, 158, 11, 0.3);
        }

        .status-indicator.delay {
            background-color: #ef4444;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.3);
        }

        .status-indicator.unknown {
            background-color: #6b7280;
            box-shadow: 0 0 8px rgba(107, 114, 128, 0.3);
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            min-height: 400px;
        }

        .chart-container canvas {
            max-height: 400px !important;
            width: 100% !important;
        }

        #detailedStatusChart {
            display: block;
            box-sizing: border-box;
            height: 400px;
            width: 100%;
        }
    </style>
    <div class="space-y-6">
        <!-- Project Status -->
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <span>Project Status Report</span>
                    <div class="flex items-center gap-3">
                        <!-- Project Selector -->
                        <select wire:model.live="selectedProjectId"
                            class="border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                            <option value="">Select Project</option>
                            @foreach ($projectOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </x-slot>

            @if ($selectedProjectId && !empty($projectStatusData))
                <div class="space-y-6">
                    <!-- Project Status Overview Cards - Sejajar dalam satu baris -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @php
                            $selectedProject = collect($statusData)->firstWhere('id', $selectedProjectId);
                            $currentStatus = $selectedProject['status'] ?? 'unknown';
                            $actualProgress = $selectedProject['actual'] ?? 0;
                            $plannedProgress = $selectedProject['planned'] ?? 0;
                            $deviation = $selectedProject['deviation'] ?? 0;
                        @endphp

                        <!-- Current Status -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-semibold text-gray-900">Current Status</h4>
                                <div class="flex items-center space-x-2">
                                    <div class="status-indicator {{ $currentStatus }}"
                                        title="{{ ucfirst($currentStatus) }}"></div>
                                    <span class="text-xs font-medium text-gray-600">{{ ucfirst($currentStatus) }}</span>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600">Actual Progress</span>
                                    <span class="text-sm font-bold text-gray-900">{{ $actualProgress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                                        style="width: {{ $actualProgress }}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Planned Progress -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-semibold text-gray-900">Planned Progress</h4>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2">
                                    </path>
                                </svg>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600">Plan Progress</span>
                                    <span class="text-sm font-bold text-gray-900">{{ $plannedProgress }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-gray-600 h-2 rounded-full transition-all duration-500"
                                        style="width: {{ $plannedProgress }}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Tracking -->
                        <div class="bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="text-sm font-semibold text-gray-900">Status Tracking</h4>
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600">Current Status</span>
                                    <div class="flex items-center space-x-1">
                                        <div class="status-indicator {{ $currentStatus }}"></div>
                                        <span class="text-xs font-medium">{{ ucfirst($currentStatus) }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600">Project Name</span>
                                    <span class="text-xs font-medium text-gray-900 truncate max-w-24"
                                        title="{{ $selectedProject['name'] ?? 'Unknown' }}">{{ $selectedProject['name'] ?? 'Unknown' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-600">Deviation</span>
                                    <span
                                        class="text-xs font-bold {{ $deviation >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $deviation >= 0 ? '+' : '' }}{{ round($deviation, 1) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Legend - Font diperkecil -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <h5 class="text-xs font-semibold text-gray-700 mb-2">Status Indicators</h5>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="flex items-center space-x-3">
                                <div class="status-indicator ontrack"></div>
                                <span class="text-xs text-gray-600">On Track - Project progressing as planned</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="status-indicator risk"></div>
                                <span class="text-xs text-gray-600">Risk of Delay - Minor delays detected</span>
                            </div>
                            <div class="flex items-center space-x-3">
                                <div class="status-indicator delay"></div>
                                <span class="text-xs text-gray-600">Delay - Significant delays detected</span>
                            </div>
                        </div>
                    </div>

                    <!-- Chart Container -->
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                        <div class="p-6 border-b border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-900">Progress Chart</h4>
                            <p class="text-sm text-gray-600 mt-1">
                                Overall project progress from start to completion
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="chart-container">
                                <canvas id="detailedStatusChart"
                                    style="display: block; width: 100%; height: 400px;"></canvas>
                            </div>
                            <!-- Debug info -->
                            <div class="mt-4 text-sm text-gray-500">
                                <p>Canvas ID: detailedStatusChart</p>
                                <p>Selected Project: {{ $selectedProjectId }}</p>
                                <p>Data Count: {{ count($projectStatusData) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <div class="text-gray-500">
                        @if (!$selectedProjectId)
                            <p>Please select a project to view detailed status report</p>
                        @else
                            <p>No status data available for the selected project</p>
                        @endif
                    </div>
                </div>
            @endif
        </x-filament::section>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            console.log('Chart.js loaded:', typeof Chart !== 'undefined');

            document.addEventListener('DOMContentLoaded', function() {
                console.log('DOM loaded, initializing charts...');
                initializeDetailedChart();
            });

            // Listen for Livewire updates
            document.addEventListener('livewire:morph', function() {
                console.log('Livewire morph event detected');
                setTimeout(() => {
                    initializeDetailedChart();
                }, 300);
            });

            // Additional event listeners for Livewire
            document.addEventListener('livewire:load', function() {
                console.log('Livewire loaded');
                setTimeout(() => {
                    initializeDetailedChart();
                }, 500);
            });

            window.addEventListener('livewire:update', function() {
                console.log('Livewire updated');
                setTimeout(() => {
                    initializeDetailedChart();
                }, 300);
            });

            // Force initialization after page fully loads
            window.addEventListener('load', function() {
                console.log('Window fully loaded');
                setTimeout(() => {
                    initializeDetailedChart();
                }, 1000);
            });

            function initializeDetailedChart() {
                const ctx = document.getElementById('detailedStatusChart');
                if (!ctx) {
                    console.log('Canvas element not found');
                    return;
                }

                const projectData = @json($projectStatusData);
                const selectedProject = @json($selectedProjectId ? $projectOptions[$selectedProjectId] ?? 'Unknown Project' : 'No Project Selected');

                console.log('Project data:', projectData);
                console.log('Selected project:', selectedProject);

                if (!projectData || projectData.length === 0) {
                    console.log('No project data available, creating chart with sample data');

                    // Create chart with sample data for testing
                    const sampleData = [{
                            period: 'Week 1',
                            planned: 25,
                            actual: 20
                        },
                        {
                            period: 'Week 2',
                            planned: 50,
                            actual: 45
                        },
                        {
                            period: 'Week 3',
                            planned: 75,
                            actual: 70
                        },
                        {
                            period: 'Week 4',
                            planned: 100,
                            actual: 85
                        }
                    ];

                    const labels = sampleData.map(item => item.period);
                    const plannedData = sampleData.map(item => item.planned);
                    const actualData = sampleData.map(item => item.actual);

                    console.log('Using sample data:', {
                        labels,
                        plannedData,
                        actualData
                    });

                    // Create chart with sample data
                    if (window.detailedStatusChart && typeof window.detailedStatusChart.destroy === 'function') {
                        window.detailedStatusChart.destroy();
                    }

                    try {
                        window.detailedStatusChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Actual Progress',
                                    data: actualData,
                                    borderColor: 'rgb(59, 130, 246)',
                                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                    borderWidth: 2,
                                    tension: 0.4,
                                    pointBackgroundColor: 'rgb(59, 130, 246)',
                                    fill: true
                                }, {
                                    label: 'Planned Progress',
                                    data: plannedData,
                                    borderColor: 'rgb(107, 114, 128)',
                                    backgroundColor: 'rgba(107, 114, 128, 0.1)',
                                    borderWidth: 2,
                                    borderDash: [5, 5],
                                    tension: 0.4,
                                    pointBackgroundColor: 'rgb(107, 114, 128)',
                                    fill: false
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    title: {
                                        display: true,
                                        text: 'Sample Progress Data (No Project Selected)'
                                    }
                                },
                                scales: {
                                    x: {
                                        display: true,
                                        title: {
                                            display: true,
                                            text: 'Time Period'
                                        }
                                    },
                                    y: {
                                        display: true,
                                        beginAtZero: true,
                                        max: 100,
                                        title: {
                                            display: true,
                                            text: 'Progress (%)'
                                        },
                                        ticks: {
                                            callback: function(value) {
                                                return value + '%';
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        console.log('Sample chart created successfully');
                    } catch (error) {
                        console.error('Error creating sample chart:', error);
                    }
                    return;
                }

                const labels = projectData.map(item => item.period);
                const plannedData = projectData.map(item => item.planned);
                const actualData = projectData.map(item => item.actual);
                const deviationData = projectData.map(item => item.deviation);

                console.log('Chart data:', {
                    labels,
                    plannedData,
                    actualData,
                    deviationData
                });

                // Destroy existing chart
                if (window.detailedStatusChart && typeof window.detailedStatusChart.destroy === 'function') {
                    window.detailedStatusChart.destroy();
                }

                try {
                    window.detailedStatusChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Actual Progress',
                                data: actualData,
                                borderColor: '#3B82F6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                borderWidth: 2,
                                tension: 0.4,
                                pointBackgroundColor: '#3B82F6',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                fill: true
                            }, {
                                label: 'Planned Progress',
                                data: plannedData,
                                borderColor: '#6B7280',
                                backgroundColor: 'rgba(107, 114, 128, 0.1)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                tension: 0.4,
                                pointBackgroundColor: '#6B7280',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                fill: false
                            }, {
                                label: 'Deviation',
                                data: deviationData,
                                borderColor: '#EF4444',
                                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                borderWidth: 2,
                                borderDash: [10, 5],
                                tension: 0.4,
                                pointBackgroundColor: '#EF4444',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                fill: false
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'top'
                                },
                                tooltip: {
                                    mode: 'index',
                                    intersect: false,
                                    callbacks: {
                                        label: function(context) {
                                            return context.dataset.label + ': ' + context.parsed.y + '%';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    display: true,
                                    title: {
                                        display: true,
                                        text: 'Time Period'
                                    }
                                },
                                y: {
                                    display: true,
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Progress (%)'
                                    },
                                    ticks: {
                                        callback: function(value) {
                                            return value + '%';
                                        }
                                    }
                                }
                            },
                            interaction: {
                                mode: 'nearest',
                                axis: 'x',
                                intersect: false
                            }
                        }
                    });

                    console.log('Chart created successfully:', window.detailedStatusChart);
                } catch (error) {
                    console.error('Error creating chart:', error);
                }
            }
        </script>
    @endpush

</x-filament-panels::page>
