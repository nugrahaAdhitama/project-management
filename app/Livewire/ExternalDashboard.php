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
use Livewire\Component;
use Livewire\WithPagination;

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

    public $ticketsByStatus = [];
    public $ticketsByPriority = [];
    public $recentTickets = [];
    public $recentActivities = [];
    public $projectStats = [];
    public $monthlyTrend = [];
    public $overdueTickets = 0;
    public $newTicketsThisWeek = 0;
    public $completedThisWeek = 0;

    // Cache gantt data to prevent re-rendering on pagination
    public $ganttDataCache = null;
    public $staticDataLoaded = false;

    protected $paginationTheme = 'tailwind';

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

        $this->loadStaticData();
        $this->loadDashboardData();
        $this->loadWidgetData();
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

        return $query->paginate(10);
    }

    public function updatingSelectedStatus()
    {
        $this->resetPage();
    }

    public function updatingSearchTerm()
    {
        $this->resetPage();
    }

    public function updatingSelectedPriority()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->selectedStatus = '';
        $this->selectedPriority = null;
        $this->searchTerm = '';
        $this->resetPage();
    }

    public function loadStaticData()
    {
        if ($this->staticDataLoaded) {
            return;
        }

        // Load gantt data once and cache it
        $this->ganttDataCache = $this->generateGanttData();

        // Load other static data that shouldn't change on pagination
        $this->recentActivities = TicketHistory::whereHas('ticket', function ($q) {
            $q->where('project_id', $this->project->id);
        })
            ->with(['ticket', 'status'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $this->staticDataLoaded = true;
    }

    public function refreshGanttData()
    {
        // Force refresh gantt data
        $this->ganttDataCache = null;
        $this->ganttDataCache = $this->generateGanttData();
        $this->dispatch('refreshGanttData');
    }

    public function gotoPage($page)
    {
        $this->setPage($page);
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
        // Return cached gantt data to prevent re-rendering on pagination
        if ($this->ganttDataCache !== null) {
            return $this->ganttDataCache;
        }

        // If cache is empty, generate data
        return $this->generateGanttData();
    }

    private function generateGanttData(): array
    {
        if (!$this->project) {
            return ['data' => [], 'links' => []];
        }

        try {
            $tickets = $this->project->tickets()
                ->select('id', 'name', 'due_date', 'start_date', 'ticket_status_id')
                ->with(['status:id,name,color'])
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
                        'is_overdue' => $isOverdue
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

    public function render()
    {
        return view('livewire.external-dashboard')
            ->layout('layouts.external');
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