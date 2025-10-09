<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserFilterCombinedTest extends TestCase
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

    public function testCombineMultipleFilters(): void
    {
        $roleModel = new Role();
        $role = $roleModel->create(['name' => 'developer']);

        $targetUser = User::factory()->create([
            'name' => 'Target User',
            'email_verified_at' => now(),
        ]);
        $targetUser->assignRole($role);
        $this->project->members()->attach($targetUser->id);

        $ticketModel = new Ticket();
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $targetUser->id,
        ]);

        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
        $unverifiedUser->assignRole($role);
        $this->project->members()->attach($unverifiedUser->id);

        $filteredUsers = User::whereHas('projects')
            ->whereHas('createdTickets')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'developer');
            })
            ->whereNotNull('email_verified_at')
            ->get();

        $this->assertCount(1, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($targetUser));
        $this->assertFalse($filteredUsers->contains($unverifiedUser));
    }

    public function testFilterUsersWithProjectsAndAssignedTickets(): void
    {
        $user1 = User::factory()->create(['name' => 'User 1']);
        $user2 = User::factory()->create(['name' => 'User 2']);
        $user3 = User::factory()->create(['name' => 'User 3']);

        $this->project->members()->attach([$user1->id, $user2->id]);

        $ticketModel = new Ticket();
        $ticket = $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $user1->id,
        ]);

        $user1->assignedTickets()->attach($ticket->id);

        $filteredUsers = User::whereHas('projects')
            ->whereHas('assignedTickets')
            ->get();

        $this->assertCount(1, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($user1));
        $this->assertFalse($filteredUsers->contains($user2));
        $this->assertFalse($filteredUsers->contains($user3));
    }

    public function testFilterUsersWithEitherProjectsOrTickets(): void
    {
        $userWithProject = User::factory()->create(['name' => 'Has Project']);
        $userWithTicket = User::factory()->create(['name' => 'Has Ticket']);
        $userWithNeither = User::factory()->create(['name' => 'Has Neither']);

        $this->project->members()->attach($userWithProject->id);

        $ticketModel = new Ticket();
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $userWithTicket->id,
        ]);

        $filteredUsers = User::where(function ($query) {
            $query->whereHas('projects')
                ->orWhereHas('createdTickets');
        })->get();

        $this->assertCount(2, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($userWithProject));
        $this->assertTrue($filteredUsers->contains($userWithTicket));
        $this->assertFalse($filteredUsers->contains($userWithNeither));
    }

    public function testFilterWithNoMatchingResults(): void
    {
        User::factory()->count(3)->create();

        $filteredUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'non-existent-role');
        })->get();

        $this->assertCount(0, $filteredUsers);
        $this->assertTrue($filteredUsers->isEmpty());
    }

    public function testFilterWithAllUsersMatching(): void
    {
        $roleModel = new Role();
        $role = $roleModel->create(['name' => 'member']);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $user1->assignRole($role);
        $user2->assignRole($role);
        $user3->assignRole($role);

        $filteredUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'member');
        })->get();

        $this->assertCount(3, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($user1));
        $this->assertTrue($filteredUsers->contains($user2));
        $this->assertTrue($filteredUsers->contains($user3));
    }

    public function testFilterPreservesUserData(): void
    {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        $this->project->members()->attach($user->id);

        $filteredUsers = User::whereHas('projects')->get();
        $retrievedUser = $filteredUsers->first();

        $this->assertEquals('John Doe', $retrievedUser->name);
        $this->assertEquals('john@example.com', $retrievedUser->email);
        $this->assertNotNull($retrievedUser->email_verified_at);
    }

    public function testFilterWithLargeDataset(): void
    {
        $users = User::factory()->count(100)->create();
        $roleModel = new Role();
        $role = $roleModel->create(['name' => 'test-role']);

        foreach ($users->take(50) as $user) {
            $user->assignRole($role);
        }

        $filteredUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'test-role');
        })->get();

        $this->assertCount(50, $filteredUsers);
    }

    public function testFilterCanBePaginated(): void
    {
        $roleModel = new Role();
        $role = $roleModel->create(['name' => 'admin']);

        for ($i = 0; $i < 25; $i++) {
            $user = User::factory()->create();
            $user->assignRole($role);
        }

        $paginatedUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->paginate(10);

        $this->assertEquals(25, $paginatedUsers->total());
        $this->assertEquals(10, $paginatedUsers->perPage());
        $this->assertCount(10, $paginatedUsers->items());
    }

    public function testFilterCountWithoutRetrievingRecords(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create();
            $this->project->members()->attach($user->id);
        }

        $count = User::whereHas('projects')->count();

        $this->assertEquals(10, $count);
    }

    public function testFilterMaintainsRelationshipAccess(): void
    {
        $user = User::factory()->create();
        $this->project->members()->attach($user->id);

        $ticketModel = new Ticket();
        $ticketModel->create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $user->id,
        ]);

        $filteredUser = User::whereHas('projects')->first();
        $projects = $filteredUser->projects;
        $createdTickets = $filteredUser->createdTickets;

        $this->assertCount(1, $projects);
        $this->assertCount(1, $createdTickets);
    }

    public function testFilterWithEagerLoading(): void
    {
        $user = User::factory()->create();
        $this->project->members()->attach($user->id);

        $filteredUsers = User::with('projects')
            ->whereHas('projects')
            ->get();

        $this->assertCount(1, $filteredUsers);
        $this->assertTrue($filteredUsers->first()->relationLoaded('projects'));
    }
}
