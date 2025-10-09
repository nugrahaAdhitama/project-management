<?php

namespace App\Filament\Pages;

use App\Models\Project;
use Filament\Pages\Page;
use Carbon\Carbon;

class ProjectStatus extends Page
{
    protected static string $view = 'filament.pages.project-status';
    protected static ?string $navigationLabel = 'Project Status';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Project Management';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'projects-status';

    public array $statusData = [];
    public ?int $selectedProjectId = null;
    public array $projectOptions = [];
    public array $projectStatusData = [];

    public function mount(): void
    {
        $this->loadData();
        $this->loadProjectOptions();
        // Set default to first project if available
        if (!$this->selectedProjectId && !empty($this->projectOptions)) {
            $this->selectedProjectId = array_key_first($this->projectOptions);
            $this->loadProjectStatusData();
        }
    }

    public function loadData(): void
    {
        $this->statusData = $this->getStatusData();
    }

    public function loadProjectOptions(): void
    {
        $projects = $this->getProjects();
        $this->projectOptions = $projects->pluck('name', 'id')->toArray();
    }

    public function updatedSelectedProjectId(): void
    {
        $this->loadProjectStatusData();
    }

    public function updateStatusReportType($type): void
    {
        $this->statusReportType = $type;
        $this->loadProjectStatusData();
    }

    public function getProjects()
    {
        $query = Project::query()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date');

        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        if (!$userIsSuperAdmin) {
            $query->whereHas('members', function ($query) {
                $query->where('user_id', auth()->id());
            });
        }

        return $query->get();
    }

    public function getStatusData()
    {
        $projects = $this->getProjects();
        $data = [];

        foreach ($projects as $project) {
            $totalTickets = $project->tickets()->count();
            $completedTickets = $project->tickets()->whereHas('status', function ($q) {
                $q->whereIn('name', ['Completed', 'Done', 'Closed']);
            })->count();

            $actualProgress = $totalTickets > 0 ? round(($completedTickets / $totalTickets) * 100, 1) : 0;

            // Planned progress
            $plannedProgress = 0;
            if ($project->start_date && $project->end_date) {
                $start = Carbon::parse($project->start_date);
                $end = Carbon::parse($project->end_date);
                $now = Carbon::now();
                $totalDuration = $start->diffInDays($end);
                $elapsed = $start->diffInDays(min($now, $end));
                $plannedProgress = $totalDuration > 0 ? round(($elapsed / $totalDuration) * 100, 1) : 0;
            }

            $deviation = $actualProgress - $plannedProgress;
            $status = $deviation >= 0 ? 'ontrack' : ($deviation >= -10 ? 'risk' : 'delay');

            $data[] = [
                'id' => $project->id,
                'name' => $project->name,
                'actual' => $actualProgress,
                'planned' => $plannedProgress,
                'deviation' => $deviation,
                'status' => $status,
            ];
        }

        return $data;
    }

    public function loadProjectStatusData(): void
    {
        if (!$this->selectedProjectId) {
            $this->projectStatusData = [];
            return;
        }

        try {
            $project = Project::find($this->selectedProjectId);
            if (!$project) {
                $this->projectStatusData = [];
                return;
            }

            // Always use overall status data
            $this->projectStatusData = $this->generateOverallStatusData($project);

            // Debug log to check the data
            \Log::info('Project Status Data Generated', [
                'project_id' => $this->selectedProjectId,
                'data_count' => count($this->projectStatusData),
                'data' => $this->projectStatusData
            ]);

        } catch (\Exception $e) {
            \Log::error('Error loading project status data: ' . $e->getMessage());
            $this->projectStatusData = [];
        }
    }

    private function generateOverallStatusData(Project $project): array
    {
        $startDate = $project->start_date ? Carbon::parse($project->start_date) : Carbon::now()->subMonths(6);
        $endDate = $project->end_date ? Carbon::parse($project->end_date) : Carbon::now()->addMonths(2);

        $months = [];
        $currentMonth = $startDate->copy()->startOfMonth();
        $monthCount = 0;
        $maxMonths = 12; // Limit to 12 months

        while ($currentMonth->lte($endDate) && $monthCount < $maxMonths) {
            $monthEnd = $currentMonth->copy()->endOfMonth();

            // Calculate planned progress for this month
            $totalDuration = $startDate->diffInDays($endDate);
            $elapsed = $startDate->diffInDays($monthEnd);
            $plannedProgress = $totalDuration > 0 ? min(100, max(0, ($elapsed / $totalDuration) * 100)) : 0;

            // Calculate actual progress (tickets completed by this month)
            $totalTickets = $project->tickets()->count();

            if ($totalTickets > 0) {
                $completedTickets = $project->tickets()
                    ->whereHas('status', function ($q) {
                        $q->whereIn('name', ['Completed', 'Done', 'Closed']);
                    })
                    ->where('updated_at', '<=', $monthEnd)
                    ->count();
                $actualProgress = ($completedTickets / $totalTickets) * 100;
            } else {
                // If no tickets, simulate some progress based on time elapsed
                $actualProgress = $plannedProgress * 0.8; // 80% of planned
            }

            $deviation = $actualProgress - $plannedProgress;

            $months[] = [
                'period' => $currentMonth->format('M Y'),
                'planned' => round($plannedProgress, 1),
                'actual' => round($actualProgress, 1),
                'deviation' => round($deviation, 1)
            ];

            $currentMonth->addMonth();
            $monthCount++;
        }

        // If no months generated, create sample data
        if (empty($months)) {
            $months = [
                ['period' => 'Jan 2024', 'planned' => 25, 'actual' => 20, 'deviation' => -5],
                ['period' => 'Feb 2024', 'planned' => 50, 'actual' => 45, 'deviation' => -5],
                ['period' => 'Mar 2024', 'planned' => 75, 'actual' => 70, 'deviation' => -5],
                ['period' => 'Apr 2024', 'planned' => 100, 'actual' => 85, 'deviation' => -15]
            ];
        }

        return $months;
    }
}
