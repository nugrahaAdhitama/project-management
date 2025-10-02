<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatisticsQueryPerformanceTest extends TestCase
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

    public function testMultipleStatisticsCanBeLoadedInSingleQuery(): void
    {
        $projectModel = new Project();
        $project = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $this->user->projects()->attach($project->id);

        $ticketModel = new Ticket();
        $ticket = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket->id);

        $user = User::withCount([
            'projects',
            'assignedTickets',
            'createdTickets',
        ])->find($this->user->id);

        $this->assertNotNull($user->projects_count);
        $this->assertNotNull($user->assigned_tickets_count);
        $this->assertNotNull($user->created_tickets_count);

        $this->assertEquals(1, $user->projects_count);
        $this->assertEquals(1, $user->assigned_tickets_count);
        $this->assertEquals(1, $user->created_tickets_count);
    }

    public function testStatisticsWithCustomAliasesUsingWithCount(): void
    {
        $projectModel = new Project();
        $project = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $this->user->projects()->attach($project->id);

        $ticketModel = new Ticket();
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $this->user->id,
        ]);

        $user = User::withCount([
            'projects as total_projects',
            'assignedTickets as total_assigned_tickets',
            'createdTickets as total_created_tickets',
        ])->find($this->user->id);

        $this->assertEquals(1, $user->total_projects);
        $this->assertEquals(0, $user->total_assigned_tickets);
        $this->assertEquals(1, $user->total_created_tickets);
    }
}
