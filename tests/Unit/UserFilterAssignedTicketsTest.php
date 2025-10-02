<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFilterAssignedTicketsTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;
    protected TicketStatus $ticketStatus;
    protected TicketPriority $ticketPriority;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function testFilterUsersWhoHaveAssignedTickets(): void
    {
        $userWithTicket = User::factory()->create(['name' => 'User With Ticket']);
        $userWithoutTicket = User::factory()->create(['name' => 'User Without Ticket']);

        $ticketModel = new Ticket();
        $ticket = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $userWithTicket->id,
        ]);

        $userWithTicket->assignedTickets()->attach($ticket->id);

        $usersWithAssignedTickets = User::whereHas('assignedTickets')->get();

        $this->assertCount(1, $usersWithAssignedTickets);
        $this->assertTrue($usersWithAssignedTickets->contains($userWithTicket));
        $this->assertFalse($usersWithAssignedTickets->contains($userWithoutTicket));
    }

    public function testFilterUsersWithMultipleAssignedTickets(): void
    {
        $user = User::factory()->create();

        $ticketModel = new Ticket();
        $ticket1 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $user->id,
        ]);

        $ticket2 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $user->id,
        ]);

        $ticket3 = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 3',
            'created_by' => $user->id,
        ]);

        $user->assignedTickets()->attach([$ticket1->id, $ticket2->id, $ticket3->id]);

        $usersWithAssignedTickets = User::whereHas('assignedTickets')->get();

        $this->assertTrue($usersWithAssignedTickets->contains($user));

        $foundUser = $usersWithAssignedTickets->firstWhere('id', $user->id);
        $this->assertEquals(3, $foundUser->assignedTickets()->count());
    }

    public function testFilterExcludesUsersWithoutAssignedTickets(): void
    {
        User::factory()->create();
        User::factory()->create();
        User::factory()->create();

        $usersWithAssignedTickets = User::whereHas('assignedTickets')->get();

        $this->assertCount(0, $usersWithAssignedTickets);
    }

    public function testFilterUsersWithAssignedTicketsInSpecificProject(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $projectModel = new Project();
        $project1 = $projectModel->create(['name' => 'Project 1', 'ticket_prefix' => 'P1']);
        $project2 = $projectModel->create(['name' => 'Project 2', 'ticket_prefix' => 'P2']);

        $statusModel = new TicketStatus();
        $status = $statusModel->create([
            'name' => 'Open P1',
            'color' => '#000000',
            'sort_order' => 1,
            'project_id' => $project1->id,
        ]);

        $ticketModel = new Ticket();
        $ticket1 = $ticketModel->create([
            'project_id' => $project1->id,
            'ticket_status_id' => $status->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket P1',
            'created_by' => $user1->id,
        ]);

        $status2 = $statusModel->create([
            'name' => 'Open P2',
            'color' => '#000000',
            'sort_order' => 1,
            'project_id' => $project2->id,
        ]);

        $ticket2 = $ticketModel->create([
            'project_id' => $project2->id,
            'ticket_status_id' => $status2->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket P2',
            'created_by' => $user2->id,
        ]);

        $user1->assignedTickets()->attach($ticket1->id);
        $user2->assignedTickets()->attach($ticket2->id);

        $usersWithTicketsInProject1 = User::whereHas('assignedTickets', function ($query) use ($project1) {
            $query->where('project_id', $project1->id);
        })->get();

        $this->assertCount(1, $usersWithTicketsInProject1);
        $this->assertTrue($usersWithTicketsInProject1->contains($user1));
        $this->assertFalse($usersWithTicketsInProject1->contains($user2));
    }
}
