<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCreatedTicketsCountTest extends TestCase
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

    public function testUserWithNoCreatedTicketsHasZeroCount(): void
    {
        $user = User::factory()->create();
        $createdTicketsCount = $user->createdTickets()->count();
        $this->assertEquals(0, $createdTicketsCount);
    }

    public function testUserWithOneCreatedTicketHasCorrectCount(): void
    {
        $ticketModel = new Ticket();
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created Ticket',
            'created_by' => $this->user->id,
        ]);

        $createdTicketsCount = $this->user->createdTickets()->count();
        $this->assertEquals(1, $createdTicketsCount);
    }

    public function testUserWithMultipleCreatedTicketsHasCorrectCount(): void
    {
        $ticketModel = new Ticket();
        
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 3',
            'created_by' => $this->user->id,
        ]);

        $createdTicketsCount = $this->user->createdTickets()->count();
        $this->assertEquals(3, $createdTicketsCount);
    }

    public function testCreatedTicketsCountUsingWithCount(): void
    {
        $ticketModel = new Ticket();
        
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $user = User::withCount('createdTickets')->find($this->user->id);
        $this->assertEquals(2, $user->created_tickets_count);
    }

    public function testCreatedTicketsCountIncreasesWhenNewTicketCreated(): void
    {
        $ticketModel = new Ticket();
        
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $initialCount = $this->user->createdTickets()->count();

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $this->user->refresh();
        $this->assertEquals($initialCount + 1, $this->user->createdTickets()->count());
    }

    public function testCreatedTicketsCountDoesNotIncludeOtherUsersTickets(): void
    {
        $otherUser = User::factory()->create();
        $ticketModel = new Ticket();

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'My Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'My Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Other Ticket 1',
            'created_by' => $otherUser->id,
        ]);

        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Other Ticket 2',
            'created_by' => $otherUser->id,
        ]);

        $userCreatedCount = $this->user->createdTickets()->count();
        $otherUserCreatedCount = $otherUser->createdTickets()->count();

        $this->assertEquals(2, $userCreatedCount);
        $this->assertEquals(2, $otherUserCreatedCount);
    }
}
