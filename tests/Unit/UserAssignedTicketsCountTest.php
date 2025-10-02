<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAssignedTicketsCountTest extends TestCase
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

    public function testUserWithNoAssignedTicketsHasZeroCount(): void
    {
        $user = User::factory()->create();
        $assignedTicketsCount = $user->assignedTickets()->count();
        $this->assertEquals(0, $assignedTicketsCount);
    }

    public function testUserWithOneAssignedTicketHasCorrectCount(): void
    {
        $ticketModel = new Ticket();
        $ticket = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'description' => 'Test Description',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket->id);
        $assignedTicketsCount = $this->user->assignedTickets()->count();
        $this->assertEquals(1, $assignedTicketsCount);
    }

    public function testUserWithMultipleAssignedTicketsHasCorrectCount(): void
    {
        $ticketModel = new Ticket();
        
        $ticket1 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticket2 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $ticket3 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 3',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach([$ticket1->id, $ticket2->id, $ticket3->id]);
        $assignedTicketsCount = $this->user->assignedTickets()->count();
        $this->assertEquals(3, $assignedTicketsCount);
    }

    public function testAssignedTicketsCountUsingWithCount(): void
    {
        $ticketModel = new Ticket();
        
        $ticket1 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticket2 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach([$ticket1->id, $ticket2->id]);
        $user = User::withCount('assignedTickets')->find($this->user->id);
        $this->assertEquals(2, $user->assigned_tickets_count);
    }

    public function testAssignedTicketsCountIncreasesWhenTicketAssigned(): void
    {
        $ticketModel = new Ticket();
        
        $ticket1 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket1->id);
        $initialCount = $this->user->assignedTickets()->count();

        $ticket2 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket2->id);
        $this->user->refresh();
        $this->assertEquals($initialCount + 1, $this->user->assignedTickets()->count());
    }

    public function testAssignedTicketsCountDecreasesWhenTicketUnassigned(): void
    {
        $ticketModel = new Ticket();
        
        $ticket1 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticket2 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach([$ticket1->id, $ticket2->id]);
        $this->assertEquals(2, $this->user->assignedTickets()->count());

        $this->user->assignedTickets()->detach($ticket1->id);
        $this->user->refresh();
        $this->assertEquals(1, $this->user->assignedTickets()->count());
    }
}
