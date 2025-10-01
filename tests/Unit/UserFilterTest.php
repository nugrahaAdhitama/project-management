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

class UserFilterTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;
    protected TicketStatus $ticketStatus;
    protected TicketPriority $ticketPriority;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->project = Project::create([
            'name' => 'Test Project',
            'description' => 'Test Description',
            'ticket_prefix' => 'TEST',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $this->ticketStatus = TicketStatus::create([
            'name' => 'Open',
            'color' => '#3B82F6',
            'sort_order' => 1,
            'is_completed' => false,
            'project_id' => $this->project->id,
        ]);

        $this->ticketPriority = TicketPriority::firstOrCreate(
            ['name' => 'Medium'],
            ['color' => '#F59E0B']
        );
    }

    // ============================================
    // FILTER: HAS PROJECTS
    // ============================================

    public function testFilterUsersWhoHaveProjects(): void
    {
        // Arrange: Create users with and without projects
        $userWithProject = User::factory()->create(['name' => 'User With Project']);
        $userWithoutProject = User::factory()->create(['name' => 'User Without Project']);

        $this->project->members()->attach($userWithProject->id);

        // Act: Filter users who have projects
        $usersWithProjects = User::whereHas('projects')->get();

        // Assert
        $this->assertCount(1, $usersWithProjects);
        $this->assertTrue($usersWithProjects->contains($userWithProject));
        $this->assertFalse($usersWithProjects->contains($userWithoutProject));
    }

    public function testFilterUsersWithMultipleProjects(): void
    {
        // Arrange: Create user with multiple projects
        $user = User::factory()->create();

        $project1 = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = Project::create(['name' => 'P2', 'ticket_prefix' => 'P2']);
        $project3 = Project::create(['name' => 'P3', 'ticket_prefix' => 'P3']);

        $user->projects()->attach([$project1->id, $project2->id, $project3->id]);

        // Act: Filter users who have projects
        $usersWithProjects = User::whereHas('projects')->get();

        // Assert
        $this->assertTrue($usersWithProjects->contains($user));

        // Verify the user actually has 3 projects
        $foundUser = $usersWithProjects->firstWhere('id', $user->id);
        $this->assertEquals(3, $foundUser->projects()->count());
    }

    public function testFilterExcludesUsersWithoutProjects(): void
    {
        // Arrange: Create multiple users without projects
        $user1 = User::factory()->create(['name' => 'User 1']);
        $user2 = User::factory()->create(['name' => 'User 2']);
        $user3 = User::factory()->create(['name' => 'User 3']);

        // Act: Filter users who have projects
        $usersWithProjects = User::whereHas('projects')->get();

        // Assert: Should return empty collection
        $this->assertCount(0, $usersWithProjects);
        $this->assertFalse($usersWithProjects->contains($user1));
        $this->assertFalse($usersWithProjects->contains($user2));
        $this->assertFalse($usersWithProjects->contains($user3));
    }

    public function testFilterUsersWithProjectsCanBeChained(): void
    {
        // Arrange: Create users with different scenarios
        $userWithProject = User::factory()->create(['name' => 'Alpha User']);
        $userWithProjectBeta = User::factory()->create(['name' => 'Beta User']);

        $this->project->members()->attach([$userWithProject->id, $userWithProjectBeta->id]);

        // Act: Chain whereHas with other conditions
        $filteredUsers = User::whereHas('projects')
            ->where('name', 'LIKE', '%Alpha%')
            ->get();

        // Assert
        $this->assertCount(1, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($userWithProject));
        $this->assertFalse($filteredUsers->contains($userWithProjectBeta));
    }

    // ============================================
    // FILTER: HAS ASSIGNED TICKETS
    // ============================================

    public function testFilterUsersWhoHaveAssignedTickets(): void
    {
        // Arrange: Create users with and without assigned tickets
        $userWithTicket = User::factory()->create(['name' => 'User With Ticket']);
        $userWithoutTicket = User::factory()->create(['name' => 'User Without Ticket']);

        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $userWithTicket->id,
        ]);

        $userWithTicket->assignedTickets()->attach($ticket->id);

        // Act: Filter users who have assigned tickets
        $usersWithAssignedTickets = User::whereHas('assignedTickets')->get();

        // Assert
        $this->assertCount(1, $usersWithAssignedTickets);
        $this->assertTrue($usersWithAssignedTickets->contains($userWithTicket));
        $this->assertFalse($usersWithAssignedTickets->contains($userWithoutTicket));
    }

    public function testFilterUsersWithMultipleAssignedTickets(): void
    {
        // Arrange: Create user with multiple assigned tickets
        $user = User::factory()->create();

        $ticket1 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $user->id,
        ]);

        $ticket2 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $user->id,
        ]);

        $ticket3 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 3',
            'created_by' => $user->id,
        ]);

        $user->assignedTickets()->attach([$ticket1->id, $ticket2->id, $ticket3->id]);

        // Act: Filter users who have assigned tickets
        $usersWithAssignedTickets = User::whereHas('assignedTickets')->get();

        // Assert
        $this->assertTrue($usersWithAssignedTickets->contains($user));

        // Verify the user actually has 3 assigned tickets
        $foundUser = $usersWithAssignedTickets->firstWhere('id', $user->id);
        $this->assertEquals(3, $foundUser->assignedTickets()->count());
    }

    public function testFilterExcludesUsersWithoutAssignedTickets(): void
    {
        // Arrange: Create multiple users without assigned tickets
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Act: Filter users who have assigned tickets
        $usersWithAssignedTickets = User::whereHas('assignedTickets')->get();

        // Assert: Should return empty collection
        $this->assertCount(0, $usersWithAssignedTickets);
    }

    public function testFilterUsersWithAssignedTicketsInSpecificProject(): void
    {
        // Arrange: Create users with tickets in different projects
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $project1 = Project::create(['name' => 'Project 1', 'ticket_prefix' => 'P1']);
        $project2 = Project::create(['name' => 'Project 2', 'ticket_prefix' => 'P2']);

        $status = TicketStatus::create([
            'name' => 'Open P1',
            'color' => '#000000',
            'sort_order' => 1,
            'project_id' => $project1->id,
        ]);

        $ticket1 = Ticket::create([
            'project_id' => $project1->id,
            'ticket_status_id' => $status->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket P1',
            'created_by' => $user1->id,
        ]);

        $status2 = TicketStatus::create([
            'name' => 'Open P2',
            'color' => '#000000',
            'sort_order' => 1,
            'project_id' => $project2->id,
        ]);

        $ticket2 = Ticket::create([
            'project_id' => $project2->id,
            'ticket_status_id' => $status2->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket P2',
            'created_by' => $user2->id,
        ]);

        $user1->assignedTickets()->attach($ticket1->id);
        $user2->assignedTickets()->attach($ticket2->id);

        // Act: Filter users who have assigned tickets in project1
        $usersWithTicketsInProject1 = User::whereHas('assignedTickets', function ($query) use ($project1) {
            $query->where('project_id', $project1->id);
        })->get();

        // Assert
        $this->assertCount(1, $usersWithTicketsInProject1);
        $this->assertTrue($usersWithTicketsInProject1->contains($user1));
        $this->assertFalse($usersWithTicketsInProject1->contains($user2));
    }

    // ============================================
    // FILTER: HAS CREATED TICKETS
    // ============================================

    public function testFilterUsersWhoHaveCreatedTickets(): void
    {
        // Arrange: Create users with and without created tickets
        $userWhoCreated = User::factory()->create(['name' => 'Creator']);
        $userWhoDidNotCreate = User::factory()->create(['name' => 'Non-Creator']);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created Ticket',
            'created_by' => $userWhoCreated->id,
        ]);

        // Act: Filter users who have created tickets
        $usersWhoCreatedTickets = User::whereHas('createdTickets')->get();

        // Assert
        $this->assertCount(1, $usersWhoCreatedTickets);
        $this->assertTrue($usersWhoCreatedTickets->contains($userWhoCreated));
        $this->assertFalse($usersWhoCreatedTickets->contains($userWhoDidNotCreate));
    }

    public function testFilterUsersWithMultipleCreatedTickets(): void
    {
        // Arrange: Create user who created multiple tickets
        $user = User::factory()->create();

        for ($i = 1; $i <= 5; $i++) {
            Ticket::create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => "Ticket $i",
                'created_by' => $user->id,
            ]);
        }

        // Act: Filter users who have created tickets
        $usersWhoCreatedTickets = User::whereHas('createdTickets')->get();

        // Assert
        $this->assertTrue($usersWhoCreatedTickets->contains($user));

        // Verify the user actually created 5 tickets
        $foundUser = $usersWhoCreatedTickets->firstWhere('id', $user->id);
        $this->assertEquals(5, $foundUser->createdTickets()->count());
    }

    public function testFilterExcludesUsersWithoutCreatedTickets(): void
    {
        // Arrange: Create multiple users who haven't created tickets
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Act: Filter users who have created tickets
        $usersWhoCreatedTickets = User::whereHas('createdTickets')->get();

        // Assert: Should return empty collection
        $this->assertCount(0, $usersWhoCreatedTickets);
    }

    public function testFilterDistinguishesCreatedFromAssignedTickets(): void
    {
        // Arrange: User who created tickets vs user who only has assigned tickets
        $creator = User::factory()->create(['name' => 'Creator']);
        $assignee = User::factory()->create(['name' => 'Assignee']);

        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $creator->id,
        ]);

        // Assign to different user
        $assignee->assignedTickets()->attach($ticket->id);

        // Act: Filter users who created tickets
        $creators = User::whereHas('createdTickets')->get();

        // Assert: Only the creator should be in results
        $this->assertCount(1, $creators);
        $this->assertTrue($creators->contains($creator));
        $this->assertFalse($creators->contains($assignee));
    }

    // ============================================
    // FILTER: BY ROLE
    // ============================================

    public function testFilterUsersBySpecificRole(): void
    {
        // Arrange: Create roles and users
        $adminRole = Role::create(['name' => 'admin']);
        $memberRole = Role::create(['name' => 'member']);

        $admin1 = User::factory()->create(['name' => 'Admin 1']);
        $admin2 = User::factory()->create(['name' => 'Admin 2']);
        $member1 = User::factory()->create(['name' => 'Member 1']);

        $admin1->assignRole($adminRole);
        $admin2->assignRole($adminRole);
        $member1->assignRole($memberRole);

        // Act: Filter users with admin role
        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        // Assert
        $this->assertCount(2, $admins);
        $this->assertTrue($admins->contains($admin1));
        $this->assertTrue($admins->contains($admin2));
        $this->assertFalse($admins->contains($member1));
    }

    public function testFilterUsersByMultipleRoles(): void
    {
        // Arrange: Create roles and users
        $adminRole = Role::create(['name' => 'admin']);
        $managerRole = Role::create(['name' => 'manager']);
        $memberRole = Role::create(['name' => 'member']);

        $admin = User::factory()->create(['name' => 'Admin']);
        $manager = User::factory()->create(['name' => 'Manager']);
        $member = User::factory()->create(['name' => 'Member']);

        $admin->assignRole($adminRole);
        $manager->assignRole($managerRole);
        $member->assignRole($memberRole);

        // Act: Filter users with admin OR manager role
        $privilegedUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['admin', 'manager']);
        })->get();

        // Assert
        $this->assertCount(2, $privilegedUsers);
        $this->assertTrue($privilegedUsers->contains($admin));
        $this->assertTrue($privilegedUsers->contains($manager));
        $this->assertFalse($privilegedUsers->contains($member));
    }

    public function testFilterUsersWithoutRoles(): void
    {
        // Arrange: Create users with and without roles
        $role = Role::create(['name' => 'admin']);

        $userWithRole = User::factory()->create(['name' => 'With Role']);
        $userWithoutRole = User::factory()->create(['name' => 'Without Role']);

        $userWithRole->assignRole($role);

        // Act: Filter users without any roles
        $usersWithoutRoles = User::whereDoesntHave('roles')->get();

        // Assert
        $this->assertTrue($usersWithoutRoles->contains($userWithoutRole));
        $this->assertFalse($usersWithoutRoles->contains($userWithRole));
    }

    public function testFilterUsersWithMultipleRolesAssigned(): void
    {
        // Arrange: Create user with multiple roles
        $adminRole = Role::create(['name' => 'admin']);
        $managerRole = Role::create(['name' => 'manager']);

        $user = User::factory()->create();
        $user->assignRole([$adminRole, $managerRole]);

        // Act: Filter users who have admin role
        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        // Assert
        $this->assertTrue($admins->contains($user));

        // Verify user has both roles
        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('manager'));
    }

    // ============================================
    // FILTER: EMAIL VERIFIED/UNVERIFIED
    // ============================================

    public function testFilterUsersWithUnverifiedEmail(): void
    {
        // Arrange: Create users with verified and unverified emails
        $verifiedUser = User::factory()->create([
            'name' => 'Verified User',
            'email_verified_at' => now(),
        ]);

        $unverifiedUser = User::factory()->create([
            'name' => 'Unverified User',
            'email_verified_at' => null,
        ]);

        // Act: Filter users with unverified email
        $unverifiedUsers = User::whereNull('email_verified_at')->get();

        // Assert
        $this->assertCount(1, $unverifiedUsers);
        $this->assertTrue($unverifiedUsers->contains($unverifiedUser));
        $this->assertFalse($unverifiedUsers->contains($verifiedUser));
    }

    public function testFilterUsersWithVerifiedEmail(): void
    {
        // Arrange: Create users with verified and unverified emails
        $verifiedUser = User::factory()->create([
            'name' => 'Verified User',
            'email_verified_at' => now(),
        ]);

        $unverifiedUser = User::factory()->create([
            'name' => 'Unverified User',
            'email_verified_at' => null,
        ]);

        // Act: Filter users with verified email
        $verifiedUsers = User::whereNotNull('email_verified_at')->get();

        // Assert
        $this->assertCount(1, $verifiedUsers);
        $this->assertTrue($verifiedUsers->contains($verifiedUser));
        $this->assertFalse($verifiedUsers->contains($unverifiedUser));
    }

    public function testFilterUnverifiedEmailsReturnsCorrectCount(): void
    {
        // Arrange: Create multiple unverified users
        User::factory()->count(5)->create([
            'email_verified_at' => null,
        ]);

        User::factory()->count(3)->create([
            'email_verified_at' => now(),
        ]);

        // Act: Filter unverified users
        $unverifiedUsers = User::whereNull('email_verified_at')->get();

        // Assert
        $this->assertCount(5, $unverifiedUsers);
    }

    public function testFilterEmailVerificationWithSpecificDate(): void
    {
        // Arrange: Create users verified at different times
        $recentlyVerified = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $oldVerified = User::factory()->create([
            'email_verified_at' => now()->subMonths(6),
        ]);

        $unverified = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Act: Filter users verified within last 30 days
        $recentlyVerifiedUsers = User::whereNotNull('email_verified_at')
            ->where('email_verified_at', '>=', now()->subDays(30))
            ->get();

        // Assert
        $this->assertCount(1, $recentlyVerifiedUsers);
        $this->assertTrue($recentlyVerifiedUsers->contains($recentlyVerified));
        $this->assertFalse($recentlyVerifiedUsers->contains($oldVerified));
        $this->assertFalse($recentlyVerifiedUsers->contains($unverified));
    }

    // ============================================
    // COMBINED FILTERS
    // ============================================

    public function testCombineMultipleFilters(): void
    {
        // Arrange: Create complex scenario
        $role = Role::create(['name' => 'developer']);

        $targetUser = User::factory()->create([
            'name' => 'Target User',
            'email_verified_at' => now(),
        ]);
        $targetUser->assignRole($role);
        $this->project->members()->attach($targetUser->id);

        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $targetUser->id,
        ]);

        // Create other users that don't match all criteria
        $unverifiedUser = User::factory()->create(['email_verified_at' => null]);
        $unverifiedUser->assignRole($role);
        $this->project->members()->attach($unverifiedUser->id);

        // Act: Apply multiple filters
        $filteredUsers = User::whereHas('projects')
            ->whereHas('createdTickets')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'developer');
            })
            ->whereNotNull('email_verified_at')
            ->get();

        // Assert: Only targetUser matches all criteria
        $this->assertCount(1, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($targetUser));
        $this->assertFalse($filteredUsers->contains($unverifiedUser));
    }

    public function testFilterUsersWithProjectsAndAssignedTickets(): void
    {
        // Arrange: Create users with different combinations
        $user1 = User::factory()->create(['name' => 'User 1']); // Has both
        $user2 = User::factory()->create(['name' => 'User 2']); // Has only project
        $user3 = User::factory()->create(['name' => 'User 3']); // Has neither

        $this->project->members()->attach([$user1->id, $user2->id]);

        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $user1->id,
        ]);

        $user1->assignedTickets()->attach($ticket->id);

        // Act: Filter users who have BOTH projects AND assigned tickets
        $filteredUsers = User::whereHas('projects')
            ->whereHas('assignedTickets')
            ->get();

        // Assert
        $this->assertCount(1, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($user1));
        $this->assertFalse($filteredUsers->contains($user2));
        $this->assertFalse($filteredUsers->contains($user3));
    }

    public function testFilterUsersWithEitherProjectsOrTickets(): void
    {
        // Arrange: Create users
        $userWithProject = User::factory()->create(['name' => 'Has Project']);
        $userWithTicket = User::factory()->create(['name' => 'Has Ticket']);
        $userWithNeither = User::factory()->create(['name' => 'Has Neither']);

        $this->project->members()->attach($userWithProject->id);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $userWithTicket->id,
        ]);

        // Act: Filter users who have projects OR created tickets
        $filteredUsers = User::where(function ($query) {
            $query->whereHas('projects')
                ->orWhereHas('createdTickets');
        })->get();

        // Assert
        $this->assertCount(2, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($userWithProject));
        $this->assertTrue($filteredUsers->contains($userWithTicket));
        $this->assertFalse($filteredUsers->contains($userWithNeither));
    }

    // ============================================
    // FILTER EDGE CASES
    // ============================================

    public function testFilterWithNoMatchingResults(): void
    {
        // Arrange: Create users without specific role
        User::factory()->count(3)->create();

        // Act: Filter users with non-existent role
        $filteredUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'non-existent-role');
        })->get();

        // Assert: Should return empty collection
        $this->assertCount(0, $filteredUsers);
        $this->assertTrue($filteredUsers->isEmpty());
    }

    public function testFilterWithAllUsersMatching(): void
    {
        // Arrange: Create users all with same role
        $role = Role::create(['name' => 'member']);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $user1->assignRole($role);
        $user2->assignRole($role);
        $user3->assignRole($role);

        // Act: Filter users with member role
        $filteredUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'member');
        })->get();

        // Assert: All users should match
        $this->assertCount(3, $filteredUsers);
        $this->assertTrue($filteredUsers->contains($user1));
        $this->assertTrue($filteredUsers->contains($user2));
        $this->assertTrue($filteredUsers->contains($user3));
    }

    public function testFilterPreservesUserData(): void
    {
        // Arrange: Create user with full data
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'email_verified_at' => now(),
        ]);

        $this->project->members()->attach($user->id);

        // Act: Filter and retrieve user
        $filteredUsers = User::whereHas('projects')->get();
        $retrievedUser = $filteredUsers->first();

        // Assert: All user data should be preserved
        $this->assertEquals('John Doe', $retrievedUser->name);
        $this->assertEquals('john@example.com', $retrievedUser->email);
        $this->assertNotNull($retrievedUser->email_verified_at);
    }

    // ============================================
    // PERFORMANCE & OPTIMIZATION TESTS
    // ============================================

    public function testFilterWithLargeDataset(): void
    {
        // Arrange: Create many users
        $users = User::factory()->count(100)->create();
        $role = Role::create(['name' => 'test-role']);

        // Assign role to first 50 users
        foreach ($users->take(50) as $user) {
            $user->assignRole($role);
        }

        // Act: Filter users with role
        $filteredUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'test-role');
        })->get();

        // Assert
        $this->assertCount(50, $filteredUsers);
    }

    public function testFilterCanBePaginated(): void
    {
        // Arrange: Create users
        $role = Role::create(['name' => 'admin']);

        for ($i = 0; $i < 25; $i++) {
            $user = User::factory()->create();
            $user->assignRole($role);
        }

        // Act: Filter with pagination
        $paginatedUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->paginate(10);

        // Assert
        $this->assertEquals(25, $paginatedUsers->total());
        $this->assertEquals(10, $paginatedUsers->perPage());
        $this->assertCount(10, $paginatedUsers->items());
    }

    public function testFilterCountWithoutRetrievingRecords(): void
    {
        // Arrange: Create users with projects
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create();
            $this->project->members()->attach($user->id);
        }

        // Act: Count filtered users without retrieving them
        $count = User::whereHas('projects')->count();

        // Assert
        $this->assertEquals(10, $count);
    }

    // ============================================
    // FILTER RELATIONSHIP INTEGRITY
    // ============================================

    public function testFilterMaintainsRelationshipAccess(): void
    {
        // Arrange: Create user with relationships
        $user = User::factory()->create();
        $this->project->members()->attach($user->id);

        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $user->id,
        ]);

        // Act: Filter and access relationships
        $filteredUser = User::whereHas('projects')->first();
        $projects = $filteredUser->projects;
        $createdTickets = $filteredUser->createdTickets;

        // Assert: Relationships should be accessible
        $this->assertCount(1, $projects);
        $this->assertCount(1, $createdTickets);
    }

    public function testFilterWithEagerLoading(): void
    {
        // Arrange: Create users with relationships
        $user = User::factory()->create();
        $this->project->members()->attach($user->id);

        // Act: Filter with eager loading
        $filteredUsers = User::with('projects')
            ->whereHas('projects')
            ->get();

        // Assert: Projects should be eager loaded
        $this->assertCount(1, $filteredUsers);
        $this->assertTrue($filteredUsers->first()->relationLoaded('projects'));
    }
}
