<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatisticsRelationshipsTest extends TestCase
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

    public function testProjectsRelationshipIsAccessible(): void
    {
        $projectModel = new Project();
        $project = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $this->user->projects()->attach($project->id);

        $projects = $this->user->projects;

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $projects);
        $this->assertCount(1, $projects);
        $this->assertEquals('P1', $projects->first()->name);
    }

    public function testAssignedTicketsRelationshipIsAccessible(): void
    {
        $ticketModel = new Ticket();
        $ticket = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket->id);
        $assignedTickets = $this->user->assignedTickets;

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $assignedTickets);
        $this->assertCount(1, $assignedTickets);
        $this->assertEquals('Test Ticket', $assignedTickets->first()->name);
    }

    public function testCreatedTicketsRelationshipIsAccessible(): void
    {
        $ticketModel = new Ticket();
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created Ticket',
            'created_by' => $this->user->id,
        ]);

        $createdTickets = $this->user->createdTickets;

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $createdTickets);
        $this->assertCount(1, $createdTickets);
        $this->assertEquals('Created Ticket', $createdTickets->first()->name);
    }

    public function testCanRetrieveStatisticsForMultipleUsersAtOnce(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $projectModel = new Project();
        $project1 = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = $projectModel->create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $user1->projects()->attach($project1->id);
        $user2->projects()->attach([$project1->id, $project2->id]);
        $user3->projects()->attach($project2->id);

        $users = User::withCount('projects')
            ->whereIn('id', [$user1->id, $user2->id, $user3->id])
            ->get();

        $this->assertCount(3, $users);

        $u1 = $users->firstWhere('id', $user1->id);
        $u2 = $users->firstWhere('id', $user2->id);
        $u3 = $users->firstWhere('id', $user3->id);

        $this->assertEquals(1, $u1->projects_count);
        $this->assertEquals(2, $u2->projects_count);
        $this->assertEquals(1, $u3->projects_count);
    }

    public function testStatisticsForAllUsersInDatabase(): void
    {
        $users = User::factory()->count(5)->create();

        $projectModel = new Project();
        $project = $projectModel->create(['name' => 'Shared Project', 'ticket_prefix' => 'SHR']);

        foreach ($users as $index => $user) {
            if ($index % 2 === 0) {
                $user->projects()->attach($project->id);
            }

            $ticketModel = new Ticket();
            for ($i = 0; $i < $index; $i++) {
                $ticketModel->create([
                    'project_id' => $this->project->id,
                    'ticket_status_id' => $this->ticketStatus->id,
                    'priority_id' => $this->ticketPriority->id,
                    'name' => "Ticket $i for user {$user->id}",
                    'created_by' => $user->id,
                ]);
            }
        }

        $usersWithStats = User::withCount(['projects', 'createdTickets'])->get();

        $this->assertGreaterThanOrEqual(5, $usersWithStats->count());

        foreach ($usersWithStats as $user) {
            $this->assertIsInt($user->projects_count);
            $this->assertIsInt($user->created_tickets_count);
        }
    }
}
