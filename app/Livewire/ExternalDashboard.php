<?php

namespace App\Livewire;

use App\Models\ExternalAccess;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.external')]
class ExternalDashboard extends Component
{
    use WithPagination;

    public $project;
    public $token;
    public $selectedStatus = '';
    public $selectedPriority = null;
    public $searchTerm = '';
    public $totalTickets = 0;
    public $completedTickets = 0;
    public $progressPercentage = 0;
    public $statuses;
    public $priorities;
    public $activeTab = 'tasks';

    public $ticketsByStatus = [];
    public $ticketsByPriority = [];
    public $recentTickets = [];
    public $projectStats = [];
    public $monthlyTrend = [];
    public $overdueTickets = 0;
    public $newTicketsThisWeek = 0;
    public $completedThisWeek = 0;

    // Project Status properties
    public $projectStatusData = [];
    public $statusReportType = 'weekly'; // 'weekly' or 'overall'
    public $currentProjectStatus = 'ontrack'; // 'ontrack', 'risk', 'delay'
    public $previousProjectStatus = 'ontrack';
    public $actualProgress = 0;
    public $plannedProgress = 0;

    // Cache gantt data to prevent re-rendering on pagination
    public $ganttDataCache = null;
    public $staticDataLoaded = false;

    protected $paginationTheme = 'tailwind';

    protected $listeners = ['refreshData'];

    public function refreshData()
    {
        try {
            // Manual refresh triggered by refresh button only
            \Log::info('Manual refresh button clicked for project: ' . $this->project->id);

            // Refresh all dynamic data
            $this->loadDashboardData();
            $this->loadWidgetData();
            $this->loadProjectStatusData();

            // Clear gantt cache to force refresh
            $this->ganttDataCache = null;
            $this->staticDataLoaded = false;

            // Force reload of paginated data
            $this->resetPage('tickets');
            $this->resetPage('activities');

            // Dispatch event to refresh gantt chart
            $this->dispatch('refreshGanttData');

            // Dispatch custom event to maintain tab state (ONLY for button refresh)
            $this->dispatch('data-refreshed');

            // Show success notification
            session()->flash('message', 'Data refreshed successfully!');

        } catch (\Exception $e) {
            \Log::error('Error refreshing dashboard data: ' . $e->getMessage());
            session()->flash('error', 'Failed to refresh data. Please try again.');
        }
    }

    public function mount($token)
    {
        $this->token = $token;

        if (!Session::get('external_authenticated_' . $token)) {
            return redirect()->route('external.login', $token);
        }

        $externalAccess = ExternalAccess::where('access_token', $token)
            ->where('is_active', true)
            ->first();

        if (!$externalAccess) {
            abort(404, 'External access not found');
        }

        $this->project = $externalAccess->project;

        $this->statuses = TicketStatus::where('project_id', $this->project->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $this->priorities = TicketPriority::orderBy('name')
            ->get();

        $externalAccess->updateLastAccessed();

        // Ensure default tab is 'tasks'
        $this->activeTab = 'tasks';

        $this->loadStaticData();
        $this->loadDashboardData();
        $this->loadWidgetData();
        $this->loadProjectStatusData();
    }

    public function getTicketsProperty()
    {
        $query = $this->project->tickets()
            ->with(['status', 'priority', 'assignees'])
            ->when($this->selectedStatus, function ($q) {
                $q->where('ticket_status_id', $this->selectedStatus);
            })
            ->when($this->selectedPriority, function ($q) {
                $q->where('priority_id', $this->selectedPriority);
            })
            ->when($this->searchTerm, function ($q) {
                $q->where(function ($query) {
                    $query->where('name', 'like', '%' . $this->searchTerm . '%')
                        ->orWhere('description', 'like', '%' . $this->searchTerm . '%')
                        ->orWhere('uuid', 'like', '%' . $this->searchTerm . '%');
                });
            })
            ->orderBy('id', 'asc');

        return $query->paginate(10, ['*'], 'tickets');
    }

    public function getRecentActivitiesProperty()
    {
        return TicketHistory::whereHas('ticket', function ($q) {
            $q->where('project_id', $this->project->id);
        })
            ->with(['ticket', 'status'])
            ->orderBy('created_at', 'desc')
            ->paginate(10, ['*'], 'activities');
    }

    public function updatingSelectedStatus()
    {
        $this->resetPage('tickets');
    }

    public function updatingSearchTerm()
    {
        $this->resetPage('tickets');
    }

    public function updatingSelectedPriority()
    {
        $this->resetPage('tickets');
    }

    public function clearFilters()
    {
        $this->selectedStatus = '';
        $this->selectedPriority = null;
        $this->searchTerm = '';
        $this->resetPage('tickets');
    }

    public function loadStaticData()
    {
        if ($this->staticDataLoaded) {
            return;
        }

        // Load gantt data once and cache it
        $this->ganttDataCache = $this->generateGanttData();

        $this->staticDataLoaded = true;
    }

    public function refreshGanttData()
    {
        // Force refresh gantt data
        $this->ganttDataCache = null;
        $this->ganttDataCache = $this->generateGanttData();
        $this->dispatch('refreshGanttData');
    }

    public function gotoPage($page, $pageName = 'tickets')
    {
        $this->setPage($page, $pageName);
        $this->dispatch('pagination-updated');
    }

    public function loadDashboardData()
    {
        $this->ticketsByStatus = TicketStatus::where('project_id', $this->project->id)
            ->withCount([
                'tickets' => function ($query) {
                    $query->where('project_id', $this->project->id);
                }
            ])
            ->orderBy('name')
            ->get()
            ->map(function ($status) {
                return [
                    'status_name' => $status->name,
                    'color' => $status->color ?? '#6B7280',
                    'count' => $status->tickets_count
                ];
            })
            ->toArray();

        $this->recentTickets = $this->project->tickets()
            ->with(['status', 'priority'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function loadWidgetData()
    {
        $remainingDays = null;
        if ($this->project->end_date) {
            $remainingDays = (int) Carbon::now()->diffInDays(Carbon::parse($this->project->end_date), false);
        }

        $this->projectStats = [
            'total_team' => $this->project->users()->count(),
            'total_tickets' => $this->project->tickets()->count(),
            'remaining_days' => $remainingDays,
            'progress_percentage' => $this->project->progress_percentage,

            'completed_tickets' => $this->project->tickets()
                ->whereHas('status', function ($q) {
                    $q->where('is_completed', true);
                })->count(),

            'in_progress_tickets' => $this->project->tickets()
                ->whereHas('status', function ($q) {
                    $q->whereIn('name', ['In Progress', 'Doing']);
                })->count(),
            'overdue_tickets' => $this->project->tickets()
                ->where('due_date', '<', Carbon::now())
                ->whereHas('status', function ($q) {
                    $q->where('is_completed', false);
                })->count(),
        ];

        $this->newTicketsThisWeek = $this->project->tickets()
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $this->completedThisWeek = $this->project->tickets()
            ->whereHas('status', function ($q) {
                $q->whereIn('name', ['Completed', 'Done', 'Closed']);
            })
            ->where('updated_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $this->monthlyTrend = $this->project->tickets()
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('count', 'month')
            ->toArray();
    }

    public function getGanttDataProperty(): array
    {
        // Check if gantt data is cached
        if ($this->ganttDataCache !== null) {
            return $this->ganttDataCache;
        }

        // Use global cache for gantt data
        $cacheKey = 'external_gantt_' . $this->project->id;
        $this->ganttDataCache = cache()->remember($cacheKey, 300, function () {
            return $this->generateGanttData();
        });

        return $this->ganttDataCache;
    }

    private function generateGanttData(): array
    {
        if (!$this->project) {
            return ['data' => [], 'links' => []];
        }

        try {
            $tickets = $this->project->tickets()
                ->select('id', 'name', 'description', 'uuid', 'due_date', 'start_date', 'ticket_status_id', 'priority_id')
                ->with(['status:id,name,color', 'priority:id,name,color', 'assignees:id,name,email'])
                ->whereNotNull('due_date')
                ->orderBy('due_date')
                ->get();

            if ($tickets->isEmpty()) {
                return ['data' => [], 'links' => []];
            }

            $ganttTasks = [];
            $now = Carbon::now();

            foreach ($tickets as $ticket) {
                if (!$ticket->due_date) {
                    continue;
                }

                try {
                    // Use start_date if available, otherwise fall back to 7 days before due_date
                    $startDate = $ticket->start_date ? Carbon::parse($ticket->start_date) : Carbon::parse($ticket->due_date)->subDays(7);
                    $endDate = Carbon::parse($ticket->due_date);

                    if ($endDate->lte($startDate)) {
                        $endDate = $startDate->copy()->addDays(1);
                    }

                    $progress = $this->getSimpleProgress($ticket->status->name ?? '') / 100;
                    $isOverdue = $endDate->lt($now) && $progress < 1;

                    // Format dates for modal
                    $startDateFormatted = $startDate->format('M d, Y');
                    $dueDateFormatted = $endDate->format('M d, Y');

                    $taskData = [
                        'id' => (string) $ticket->id,
                        'text' => $this->truncateName($ticket->name ?? 'Untitled Ticket'),
                        'start_date' => $startDate->format('d-m-Y H:i'),
                        'end_date' => $endDate->format('d-m-Y H:i'),
                        'duration' => max(1, $startDate->diffInDays($endDate)),
                        'progress' => max(0, min(1, $progress)),
                        'type' => 'task',
                        'readonly' => true,
                        'color' => $isOverdue ? '#ef4444' : ($ticket->status->color ?? '#3b82f6'),
                        'textColor' => '#ffffff',
                        'status' => $ticket->status->name ?? 'Unknown',
                        'is_overdue' => $isOverdue,
                        // Include full ticket details for modal
                        'ticket_details' => [
                            'id' => $ticket->id,
                            'uuid' => $ticket->uuid,
                            'name' => $ticket->name,
                            'description' => $ticket->description ?? 'No description available',
                            'status' => [
                                'name' => $ticket->status->name ?? 'Unknown',
                                'color' => $ticket->status->color ?? '#6B7280'
                            ],
                            'priority' => [
                                'name' => $ticket->priority->name ?? 'Normal',
                                'color' => $ticket->priority->color ?? '#6B7280'
                            ],
                            'start_date' => $startDateFormatted,
                            'due_date' => $dueDateFormatted,
                            'progress_percentage' => (int) ($progress * 100),
                            'is_overdue' => $isOverdue,
                            'assignees' => $ticket->assignees->map(function ($user) {
                                return [
                                    'name' => $user->name,
                                    'email' => $user->email
                                ];
                            })->toArray()
                        ]
                    ];

                    $ganttTasks[] = $taskData;

                } catch (\Exception $e) {
                    Log::error('Error processing ticket ' . $ticket->id . ': ' . $e->getMessage());
                    continue;
                }
            }

            return [
                'data' => $ganttTasks,
                'links' => []
            ];

        } catch (\Exception $e) {
            Log::error('Error generating gantt data: ' . $e->getMessage());
            return ['data' => [], 'links' => []];
        }
    }

    private function truncateName($name, $length = 50): string
    {
        return strlen($name) > $length ? substr($name, 0, $length) . '...' : $name;
    }

    private function getSimpleProgress($statusName): int
    {
        if (!$this->project || empty($statusName)) {
            return 0;
        }

        try {
            $statuses = $this->project->ticketStatuses()
                ->orderBy('sort_order')
                ->get();

            if ($statuses->isEmpty()) {
                return 0;
            }

            $currentStatus = $statuses->firstWhere('name', $statusName);

            if (!$currentStatus) {
                return 0;
            }

            $totalStatuses = $statuses->count();
            $currentPosition = $statuses->search(function ($status) use ($currentStatus) {
                return $status->id === $currentStatus->id;
            });

            if ($currentPosition === false) {
                return 0;
            }

            $progress = (($currentPosition + 1) / $totalStatuses) * 100;

            return (int) round(max(0, min(100, $progress)));
        } catch (\Exception $e) {
            \Log::error('Error calculating progress: ' . $e->getMessage());
            return 0;
        }
    }

    public function switchTab($tabName)
    {
        $this->activeTab = $tabName;

        // Force refresh gantt when switching to timeline
        if ($tabName === 'timeline') {
            $this->dispatch('switch-to-timeline');
        }

        // Load project status data when switching to status tab
        if ($tabName === 'status') {
            $this->loadProjectStatusData();
        }
    }

    public function updateStatusReportType($type)
    {
        $this->statusReportType = $type;
        $this->loadProjectStatusData();

        // Dispatch event to update chart
        $this->dispatch('statusReportUpdated');
    }

    public function loadProjectStatusData()
    {
        try {
            if ($this->statusReportType === 'weekly') {
                $this->projectStatusData = $this->generateWeeklyStatusData();
            } else {
                $this->projectStatusData = $this->generateOverallStatusData();
            }

            $this->calculateProjectStatus();
        } catch (\Exception $e) {
            \Log::error('Error loading project status data: ' . $e->getMessage());
            $this->projectStatusData = [];
        }
    }

    private function generateWeeklyStatusData(): array
    {
        $startDate = $this->project->start_date ? Carbon::parse($this->project->start_date) : Carbon::now()->subMonths(3);
        $endDate = $this->project->end_date ? Carbon::parse($this->project->end_date) : Carbon::now()->addMonths(1);

        $weeks = [];
        $currentWeek = $startDate->copy()->startOfWeek();

        while ($currentWeek->lte($endDate)) {
            $weekEnd = $currentWeek->copy()->endOfWeek();

            // Calculate planned progress for this week
            $totalWeeks = $startDate->diffInWeeks($endDate);
            $weekNumber = $startDate->diffInWeeks($currentWeek) + 1;
            $plannedProgress = $totalWeeks > 0 ? ($weekNumber / $totalWeeks) * 100 : 0;

            // Calculate actual progress (tickets completed by this week)
            $completedTickets = $this->project->tickets()
                ->whereHas('status', function ($q) {
                    $q->where('is_completed', true);
                })
                ->where('updated_at', '<=', $weekEnd)
                ->count();

            $totalTickets = $this->project->tickets()->count();
            $actualProgress = $totalTickets > 0 ? ($completedTickets / $totalTickets) * 100 : 0;

            // Calculate deviation
            $deviation = $actualProgress - $plannedProgress;

            $weeks[] = [
                'period' => $currentWeek->format('M d'),
                'week_start' => $currentWeek->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'planned' => round($plannedProgress, 1),
                'actual' => round($actualProgress, 1),
                'deviation' => round($deviation, 1),
                'status' => $this->determineWeekStatus($deviation)
            ];

            $currentWeek->addWeek();
        }

        return $weeks;
    }

    private function generateOverallStatusData(): array
    {
        $startDate = $this->project->start_date ? Carbon::parse($this->project->start_date) : Carbon::now()->subMonths(3);
        $endDate = $this->project->end_date ? Carbon::parse($this->project->end_date) : Carbon::now()->addMonths(1);

        $months = [];
        $currentMonth = $startDate->copy()->startOfMonth();

        while ($currentMonth->lte($endDate)) {
            $monthEnd = $currentMonth->copy()->endOfMonth();

            // Calculate planned progress for this month
            $totalDuration = $startDate->diffInDays($endDate);
            $daysPassed = $startDate->diffInDays($currentMonth->copy()->endOfMonth());
            $plannedProgress = $totalDuration > 0 ? ($daysPassed / $totalDuration) * 100 : 0;

            // Calculate actual progress
            $completedTickets = $this->project->tickets()
                ->whereHas('status', function ($q) {
                    $q->where('is_completed', true);
                })
                ->where('updated_at', '<=', $monthEnd)
                ->count();

            $totalTickets = $this->project->tickets()->count();
            $actualProgress = $totalTickets > 0 ? ($completedTickets / $totalTickets) * 100 : 0;

            // Calculate deviation
            $deviation = $actualProgress - $plannedProgress;

            $months[] = [
                'period' => $currentMonth->format('M Y'),
                'month_start' => $currentMonth->format('Y-m-d'),
                'month_end' => $monthEnd->format('Y-m-d'),
                'planned' => round(max(0, min(100, $plannedProgress)), 1),
                'actual' => round($actualProgress, 1),
                'deviation' => round($deviation, 1),
                'status' => $this->determineWeekStatus($deviation)
            ];

            $currentMonth->addMonth();
        }

        return $months;
    }

    private function calculateProjectStatus()
    {
        // Store previous status
        $this->previousProjectStatus = $this->currentProjectStatus;

        // Calculate current actual and planned progress
        $totalTickets = $this->project->tickets()->count();
        $completedTickets = $this->project->tickets()
            ->whereHas('status', function ($q) {
                $q->where('is_completed', true);
            })->count();

        $this->actualProgress = $totalTickets > 0 ? round(($completedTickets / $totalTickets) * 100, 1) : 0;

        // Calculate planned progress based on timeline
        if ($this->project->start_date && $this->project->end_date) {
            $startDate = Carbon::parse($this->project->start_date);
            $endDate = Carbon::parse($this->project->end_date);
            $now = Carbon::now();

            if ($now->gte($endDate)) {
                $this->plannedProgress = 100;
            } elseif ($now->lte($startDate)) {
                $this->plannedProgress = 0;
            } else {
                $totalDuration = $startDate->diffInDays($endDate);
                $daysPassed = $startDate->diffInDays($now);
                $this->plannedProgress = $totalDuration > 0 ? round(($daysPassed / $totalDuration) * 100, 1) : 0;
            }
        } else {
            $this->plannedProgress = $this->actualProgress; // If no timeline, assume on track
        }

        // Determine current status
        $deviation = $this->actualProgress - $this->plannedProgress;
        $this->currentProjectStatus = $this->determineWeekStatus($deviation);
    }

    private function determineWeekStatus($deviation): string
    {
        if ($deviation >= 0) {
            return 'ontrack'; // Green - on track or ahead
        } elseif ($deviation >= -10) {
            return 'risk'; // Yellow - risk of delay (within 10% behind)
        } else {
            return 'delay'; // Red - significant delay (more than 10% behind)
        }
    }

    public function exportGanttData()
    {
        try {
            $ganttData = $this->ganttData;

            // Log export activity
            \Log::info('Gantt chart export requested for project: ' . $this->project->id);

            // Return the gantt data for frontend processing
            return response()->json([
                'success' => true,
                'data' => $ganttData,
                'project_name' => $this->project->name,
                'export_timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            \Log::error('Error exporting gantt data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export gantt data'
            ], 500);
        }
    }
    public function render()
    {
        return view('livewire.external-dashboard');
    }

    public function logout()
    {
        try {
            if (auth()->check()) {
                auth()->guard()->logout();
            }
        } catch (\Throwable $e) {
        }

        \Illuminate\Support\Facades\Session::forget('external_authenticated_' . $this->token);

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        // Hard redirect (langsung pindah halaman)
        return redirect()->route('external.login', ['token' => $this->token]);
    }
}