<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFilterProjectsTest extends TestCase
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

    public function testFilterUsersWhoHaveProjects(): void
    {
        $userWithProject = User::factory()->create(['name' => 'User With Project']);
        $userWithoutProject = User::factory()->create(['name' => 'User Without Project']);

        $this->project->members()->attach($userWithProject->id);

        $usersWithProjects = User::whereHas('projects')->get();

        $this->assertCount(1, $usersWithProjects);
        $this->assertTrue($usersWithProjects->contains($userWithProject));
        $this->assertFalse($usersWithProjects->contains($userWithoutProject));
    }

    public function testFilterUsersWithMultipleProjects(): void
    {
        $user = User::factory()->create();

        $projectModel = new Project();
        $project1 = $projectModel->create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = $projectModel->create(['name' => 'P2', 'ticket_prefix' => 'P2']);
        $project3 = $projectModel->create(['name' => 'P3', 'ticket_prefix' => 'P3']);

        $user->projects()->attach([$project1->id, $project2->id, $project3->id]);

        $usersWithProjects = User::whereHas('projects')->get();

        $this->assertTrue($usersWithProjects->contains($user));

        $foundUser = $usersWithProjects->firstWhere('id', $user->id);
        $this->assertEquals(3, $foundUser->projects()->count());
    }

    public function testFilterExcludesUsersWithoutProjects(): void
    {
        User::factory()->create(['name' => 'User 1']);
        User::factory()->create(['name' => 'User 2']);
        User::factory()->create(['name' => 'User 3']);

        $usersWithProjects = User::whereHas('projects')->get();

        $this->assertCount(0, $usersWithProjects);
    }

    public function testFilterUsersWithProjectsCanBeChained(): void
    {
        $userWithProject = User::factory()->create(['name' => 'Alpha User']);
        $userWithProjectBeta = User::factory()->create(['name' => 'Beta User']);

        $this->project->members()->attach([$userWithProject->id, $userWithProjectBeta->id]);

        $filteredUsers = User::whereHas('projects')
            ->where('name', 'LIKE', '%Alpha%')
            ->get();

        $this->assertCount(1, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($userWithProject));
        $this->assertFalse($filteredUsers->contains($userWithProjectBeta));
    }
}
