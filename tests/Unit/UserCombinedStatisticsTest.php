<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCombinedStatisticsTest extends TestCase
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

    public function testUserStatisticsWithAllCountersAtZero(): void
    {
        $user = User::factory()->create();
        $user = User::withCount(['projects', 'assignedTickets', 'createdTickets'])->find($user->id);

        $this->assertEquals(0, $user->projects_count);
        $this->assertEquals(0, $user->assigned_tickets_count);
        $this->assertEquals(0, $user->created_tickets_count);
    }

    public function testUserStatisticsWithAllCountersPopulated(): void
    {
        $projectModel = new Project();
        $project1 = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = $projectModel->create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $this->user->projects()->attach([$project1->id, $project2->id]);

        $otherUser = User::factory()->create();
        $ticketModel = new Ticket();
        
        $assignedTicket1 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Assigned 1',
            'created_by' => $otherUser->id,
        ]);

        $assignedTicket2 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Assigned 2',
            'created_by' => $otherUser->id,
        ]);

        $this->user->assignedTickets()->attach([$assignedTicket1->id, $assignedTicket2->id]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created 1',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created 2',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created 3',
            'created_by' => $this->user->id,
        ]);

        $user = User::withCount(['projects', 'assignedTickets', 'createdTickets'])->find($this->user->id);

        $this->assertEquals(2, $user->projects_count);
        $this->assertEquals(2, $user->assigned_tickets_count);
        $this->assertEquals(3, $user->created_tickets_count);
    }

    public function testUserCanBeAssignedToTicketTheyCreated(): void
    {
        $ticketModel = new Ticket();
        $ticket = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'My Ticket',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket->id);
        $user = User::withCount(['assignedTickets', 'createdTickets'])->find($this->user->id);

        $this->assertEquals(1, $user->assigned_tickets_count);
        $this->assertEquals(1, $user->created_tickets_count);
    }

    public function testStatisticsAreIndependentAcrossMultipleUsers(): void
    {
        $user1 = User::factory()->create(['name' => 'User 1']);
        $user2 = User::factory()->create(['name' => 'User 2']);
        $user3 = User::factory()->create(['name' => 'User 3']);

        $projectModel = new Project();
        $project1 = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = $projectModel->create(['name' => 'P2', 'ticket_prefix' => 'P2']);
        $project3 = $projectModel->create(['name' => 'P3', 'ticket_prefix' => 'P3']);

        $user1->projects()->attach($project1->id);
        $user2->projects()->attach([$project1->id, $project2->id]);
        $user3->projects()->attach([$project1->id, $project2->id, $project3->id]);

        $ticketModel = new Ticket();
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $user1->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T2',
            'created_by' => $user2->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T3',
            'created_by' => $user2->id,
        ]);

        $users = User::withCount(['projects', 'createdTickets'])
            ->whereIn('id', [$user1->id, $user2->id, $user3->id])
            ->get();

        $user1Stats = $users->firstWhere('id', $user1->id);
        $user2Stats = $users->firstWhere('id', $user2->id);
        $user3Stats = $users->firstWhere('id', $user3->id);

        $this->assertEquals(1, $user1Stats->projects_count);
        $this->assertEquals(1, $user1Stats->created_tickets_count);

        $this->assertEquals(2, $user2Stats->projects_count);
        $this->assertEquals(2, $user2Stats->created_tickets_count);

        $this->assertEquals(3, $user3Stats->projects_count);
        $this->assertEquals(0, $user3Stats->created_tickets_count);
    }
}
