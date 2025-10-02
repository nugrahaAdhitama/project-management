<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFilterCreatedTicketsTest extends TestCase
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

    public function testFilterUsersWhoHaveCreatedTickets(): void
    {
        $userWhoCreated = User::factory()->create(['name' => 'Creator']);
        $userWhoDidNotCreate = User::factory()->create(['name' => 'Non-Creator']);

        $ticketModel = new Ticket();
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created Ticket',
            'created_by' => $userWhoCreated->id,
        ]);

        $usersWhoCreatedTickets = User::whereHas('createdTickets')->get();

        $this->assertCount(1, $usersWhoCreatedTickets);
        $this->assertTrue($usersWhoCreatedTickets->contains($userWhoCreated));
        $this->assertFalse($usersWhoCreatedTickets->contains($userWhoDidNotCreate));
    }

    public function testFilterUsersWithMultipleCreatedTickets(): void
    {
        $user = User::factory()->create();

        $ticketModel = new Ticket();
        for ($i = 1; $i <= 5; $i++) {
            $ticketModel->create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => "Ticket $i",
                'created_by' => $user->id,
            ]);
        }

        $usersWhoCreatedTickets = User::whereHas('createdTickets')->get();

        $this->assertTrue($usersWhoCreatedTickets->contains($user));

        $foundUser = $usersWhoCreatedTickets->firstWhere('id', $user->id);
        $this->assertEquals(5, $foundUser->createdTickets()->count());
    }

    public function testFilterExcludesUsersWithoutCreatedTickets(): void
    {
        User::factory()->create();
        User::factory()->create();

        $usersWhoCreatedTickets = User::whereHas('createdTickets')->get();

        $this->assertCount(0, $usersWhoCreatedTickets);
    }

    public function testFilterDistinguishesCreatedFromAssignedTickets(): void
    {
        $creator = User::factory()->create(['name' => 'Creator']);
        $assignee = User::factory()->create(['name' => 'Assignee']);

        $ticketModel = new Ticket();
        $ticket = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $creator->id,
        ]);

        $assignee->assignedTickets()->attach($ticket->id);

        $creators = User::whereHas('createdTickets')->get();

        $this->assertCount(1, $creators);
        $this->assertTrue($creators->contains($creator));
        $this->assertFalse($creators->contains($assignee));
    }
}
