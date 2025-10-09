<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatisticsAccuracyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected TicketStatus $ticketStatus;
    protected TicketPriority $ticketPriority;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
        ]);

        $projectModel = new Project();
        $this->project = $projectModel->create([
            'name' => 'Test Project',
            'description' => 'Test Description',
            'ticket_prefix' => 'TEST',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $statusModel = new TicketStatus();
        $this->ticketStatus = $statusModel->create([
            'name' => 'Open',
            'color' => '#3B82F6',
            'sort_order' => 1,
            'is_completed' => false,
            'project_id' => $this->project->id,
        ]);

        $priorityModel = new TicketPriority();
        $this->ticketPriority = $priorityModel->firstOrCreate(
            ['name' => 'Medium'],
            ['color' => '#F59E0B']
        );
    }

    public function testProjectsCountMatchesActualProjectMembership(): void
    {
        $projectModel = new Project();
        $projects = collect([
            $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']),
            $projectModel->create(['name' => 'P2', 'ticket_prefix' => 'P2']),
            $projectModel->create(['name' => 'P3', 'ticket_prefix' => 'P3']),
        ]);

        $this->user->projects()->attach($projects->pluck('id')->toArray());

        $relationshipCount = $this->user->projects()->count();
        $user = User::withCount('projects')->find($this->user->id);

        $this->assertEquals(3, $relationshipCount);
        $this->assertEquals(3, $user->projects_count);
        $this->assertEquals($relationshipCount, $user->projects_count);
    }

    public function testAssignedTicketsCountMatchesActualAssignments(): void
    {
        $ticketModel = new Ticket();
        $tickets = collect([
            $ticketModel->create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => 'T1',
                'created_by' => $this->user->id,
            ]),
            $ticketModel->create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => 'T2',
                'created_by' => $this->user->id,
            ]),
            $ticketModel->create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => 'T3',
                'created_by' => $this->user->id,
            ]),
        ]);

        $this->user->assignedTickets()->attach($tickets->pluck('id')->toArray());

        $relationshipCount = $this->user->assignedTickets()->count();
        $user = User::withCount('assignedTickets')->find($this->user->id);

        $this->assertEquals(3, $relationshipCount);
        $this->assertEquals(3, $user->assigned_tickets_count);
        $this->assertEquals($relationshipCount, $user->assigned_tickets_count);
    }

    public function testCreatedTicketsCountMatchesActualCreations(): void
    {
        $ticketModel = new Ticket();
        
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T2',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T3',
            'created_by' => $this->user->id,
        ]);

        $relationshipCount = $this->user->createdTickets()->count();
        $user = User::withCount('createdTickets')->find($this->user->id);

        $this->assertEquals(3, $relationshipCount);
        $this->assertEquals(3, $user->created_tickets_count);
        $this->assertEquals($relationshipCount, $user->created_tickets_count);
    }
}
