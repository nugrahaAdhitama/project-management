<?php

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\Widget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ProjectStatus extends Widget
{
    protected static string $view = 'filament.widgets.project-status';
    protected int|string|array $columnSpan = 'full';
    static ?int $sort = 5;

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

    protected function getViewData(): array
    {
        return [
            'statusData' => $this->getStatusData(),
        ];
    }
}
