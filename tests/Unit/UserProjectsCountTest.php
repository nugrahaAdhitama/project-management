<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProjectsCountTest extends TestCase
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

    public function testUserWithNoProjectsHasZeroProjectsCount(): void
    {
        $user = User::factory()->create();
        $projectsCount = $user->projects()->count();
        $this->assertEquals(0, $projectsCount);
    }

    public function testUserWithOneProjectHasCorrectProjectsCount(): void
    {
        $this->project->members()->attach($this->user->id);
        $projectsCount = $this->user->projects()->count();
        $this->assertEquals(1, $projectsCount);
    }

    public function testUserWithMultipleProjectsHasCorrectProjectsCount(): void
    {
        $projectModel = new Project();
        
        $project1 = $projectModel->create([
            'name' => 'Project 1',
            'description' => 'Description 1',
            'ticket_prefix' => 'PRJ1',
        ]);

        $project2 = $projectModel->create([
            'name' => 'Project 2',
            'description' => 'Description 2',
            'ticket_prefix' => 'PRJ2',
        ]);

        $project3 = $projectModel->create([
            'name' => 'Project 3',
            'description' => 'Description 3',
            'ticket_prefix' => 'PRJ3',
        ]);

        $this->user->projects()->attach([$project1->id, $project2->id, $project3->id]);
        $projectsCount = $this->user->projects()->count();
        $this->assertEquals(3, $projectsCount);
    }

    public function testProjectsCountUsingWithCount(): void
    {
        $projectModel = new Project();
        $project1 = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = $projectModel->create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $this->user->projects()->attach([$project1->id, $project2->id]);
        $user = User::withCount('projects')->find($this->user->id);
        $this->assertEquals(2, $user->projects_count);
    }

    public function testProjectsCountRemainsAccurateAfterAddingNewProject(): void
    {
        $this->project->members()->attach($this->user->id);
        $initialCount = $this->user->projects()->count();

        $projectModel = new Project();
        $newProject = $projectModel->create([
            'name' => 'New Project',
            'ticket_prefix' => 'NEW',
        ]);
        $newProject->members()->attach($this->user->id);

        $this->user->refresh();
        $this->assertEquals($initialCount + 1, $this->user->projects()->count());
    }

    public function testProjectsCountDecreasesWhenUserRemovedFromProject(): void
    {
        $projectModel = new Project();
        $project1 = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = $projectModel->create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $this->user->projects()->attach([$project1->id, $project2->id]);
        $this->assertEquals(2, $this->user->projects()->count());

        $this->user->projects()->detach($project1->id);
        $this->user->refresh();
        $this->assertEquals(1, $this->user->projects()->count());
    }
}
