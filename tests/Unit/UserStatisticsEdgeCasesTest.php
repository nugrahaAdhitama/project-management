<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatisticsEdgeCasesTest extends TestCase
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

    public function testStatisticsWithLargeNumberOfItems(): void
    {
        $projectModel = new Project();
        $projectIds = [];
        
        for ($i = 0; $i < 50; $i++) {
            $project = $projectModel->create([
                'name' => "Project $i",
                'ticket_prefix' => "P$i",
            ]);
            $projectIds[] = $project->id;
        }

        $this->user->projects()->attach($projectIds);

        $ticketModel = new Ticket();
        for ($i = 0; $i < 100; $i++) {
            $ticketModel->create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => "Ticket $i",
                'created_by' => $this->user->id,
            ]);
        }

        $user = User::withCount(['projects', 'createdTickets'])->find($this->user->id);

        $this->assertEquals(50, $user->projects_count);
        $this->assertEquals(100, $user->created_tickets_count);
    }

    public function testStatisticsAfterProjectDeletion(): void
    {
        $projectModel = new Project();
        $project1 = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = $projectModel->create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $this->user->projects()->attach([$project1->id, $project2->id]);
        $this->assertEquals(2, $this->user->projects()->count());

        $project1->delete();
        $this->user->refresh();
        $this->assertEquals(1, $this->user->projects()->count());
    }

    public function testStatisticsAfterTicketDeletion(): void
    {
        $ticketModel = new Ticket();
        
        $ticket1 = $ticketModel->create([
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

        $this->assertEquals(3, $this->user->createdTickets()->count());

        $ticket1->delete();
        $this->user->refresh();
        $this->assertEquals(2, $this->user->createdTickets()->count());
    }
}
