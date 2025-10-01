<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Project $project;
    protected TicketStatus $ticketStatus;
    protected TicketPriority $ticketPriority;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user for testing
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
        ]);

        // Create a project for testing
        $this->project = Project::create([
            'name' => 'Test Project',
            'description' => 'Test Description',
            'ticket_prefix' => 'TEST',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        // Create ticket status for testing
        $this->ticketStatus = TicketStatus::create([
            'name' => 'Open',
            'color' => '#3B82F6',
            'sort_order' => 1,
            'is_completed' => false,
            'project_id' => $this->project->id,
        ]);

        // Create ticket priority for testing (use firstOrCreate to avoid duplicate issues)
        $this->ticketPriority = TicketPriority::firstOrCreate(
            ['name' => 'Medium'],
            ['color' => '#F59E0B']
        );
    }

    // ============================================
    // PROJECTS COUNT TESTS
    // ============================================

    public function testUserWithNoProjectsHasZeroProjectsCount(): void
    {
        // Arrange: User without any projects
        $user = User::factory()->create();

        // Act
        $projectsCount = $user->projects()->count();

        // Assert
        $this->assertEquals(0, $projectsCount);
    }

    public function testUserWithOneProjectHasCorrectProjectsCount(): void
    {
        // Arrange: Add user as member to one project
        $this->project->members()->attach($this->user->id);

        // Act
        $projectsCount = $this->user->projects()->count();

        // Assert
        $this->assertEquals(1, $projectsCount);
    }

    public function testUserWithMultipleProjectsHasCorrectProjectsCount(): void
    {
        // Arrange: Create multiple projects and add user as member
        $project1 = Project::create([
            'name' => 'Project 1',
            'description' => 'Description 1',
            'ticket_prefix' => 'PRJ1',
        ]);

        $project2 = Project::create([
            'name' => 'Project 2',
            'description' => 'Description 2',
            'ticket_prefix' => 'PRJ2',
        ]);

        $project3 = Project::create([
            'name' => 'Project 3',
            'description' => 'Description 3',
            'ticket_prefix' => 'PRJ3',
        ]);

        $this->user->projects()->attach([$project1->id, $project2->id, $project3->id]);

        // Act
        $projectsCount = $this->user->projects()->count();

        // Assert
        $this->assertEquals(3, $projectsCount);
    }

    public function testProjectsCountUsingWithCount(): void
    {
        // Arrange: Add user to multiple projects
        $project1 = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = Project::create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $this->user->projects()->attach([$project1->id, $project2->id]);

        // Act: Use withCount like in UserStatisticsChart
        $user = User::withCount('projects')->find($this->user->id);

        // Assert
        $this->assertEquals(2, $user->projects_count);
    }

    public function testProjectsCountRemainsAccurateAfterAddingNewProject(): void
    {
        // Arrange: Initial state
        $this->project->members()->attach($this->user->id);
        $initialCount = $this->user->projects()->count();

        // Act: Add new project
        $newProject = Project::create([
            'name' => 'New Project',
            'ticket_prefix' => 'NEW',
        ]);
        $newProject->members()->attach($this->user->id);

        // Assert
        $this->user->refresh();
        $this->assertEquals($initialCount + 1, $this->user->projects()->count());
    }

    public function testProjectsCountDecreasesWhenUserRemovedFromProject(): void
    {
        // Arrange: User in 2 projects
        $project1 = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = Project::create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $this->user->projects()->attach([$project1->id, $project2->id]);
        $this->assertEquals(2, $this->user->projects()->count());

        // Act: Remove user from one project
        $this->user->projects()->detach($project1->id);

        // Assert
        $this->user->refresh();
        $this->assertEquals(1, $this->user->projects()->count());
    }

    // ============================================
    // ASSIGNED TICKETS COUNT TESTS
    // ============================================

    public function testUserWithNoAssignedTicketsHasZeroCount(): void
    {
        // Arrange: User without any assigned tickets
        $user = User::factory()->create();

        // Act
        $assignedTicketsCount = $user->assignedTickets()->count();

        // Assert
        $this->assertEquals(0, $assignedTicketsCount);
    }

    public function testUserWithOneAssignedTicketHasCorrectCount(): void
    {
        // Arrange: Create ticket and assign to user
        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'description' => 'Test Description',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket->id);

        // Act
        $assignedTicketsCount = $this->user->assignedTickets()->count();

        // Assert
        $this->assertEquals(1, $assignedTicketsCount);
    }

    public function testUserWithMultipleAssignedTicketsHasCorrectCount(): void
    {
        // Arrange: Create multiple tickets and assign to user
        $ticket1 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticket2 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $ticket3 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 3',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach([$ticket1->id, $ticket2->id, $ticket3->id]);

        // Act
        $assignedTicketsCount = $this->user->assignedTickets()->count();

        // Assert
        $this->assertEquals(3, $assignedTicketsCount);
    }

    public function testAssignedTicketsCountUsingWithCount(): void
    {
        // Arrange: Create and assign tickets
        $ticket1 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticket2 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach([$ticket1->id, $ticket2->id]);

        // Act: Use withCount like in UserStatisticsChart
        $user = User::withCount('assignedTickets')->find($this->user->id);

        // Assert
        $this->assertEquals(2, $user->assigned_tickets_count);
    }

    public function testAssignedTicketsCountIncreasesWhenTicketAssigned(): void
    {
        // Arrange: Initial state with one ticket
        $ticket1 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket1->id);
        $initialCount = $this->user->assignedTickets()->count();

        // Act: Assign new ticket
        $ticket2 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket2->id);

        // Assert
        $this->user->refresh();
        $this->assertEquals($initialCount + 1, $this->user->assignedTickets()->count());
    }

    public function testAssignedTicketsCountDecreasesWhenTicketUnassigned(): void
    {
        // Arrange: User with 2 assigned tickets
        $ticket1 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $ticket2 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach([$ticket1->id, $ticket2->id]);
        $this->assertEquals(2, $this->user->assignedTickets()->count());

        // Act: Unassign one ticket
        $this->user->assignedTickets()->detach($ticket1->id);

        // Assert
        $this->user->refresh();
        $this->assertEquals(1, $this->user->assignedTickets()->count());
    }

    // ============================================
    // CREATED TICKETS COUNT TESTS
    // ============================================

    public function testUserWithNoCreatedTicketsHasZeroCount(): void
    {
        // Arrange: User without any created tickets
        $user = User::factory()->create();

        // Act
        $createdTicketsCount = $user->createdTickets()->count();

        // Assert
        $this->assertEquals(0, $createdTicketsCount);
    }

    public function testUserWithOneCreatedTicketHasCorrectCount(): void
    {
        // Arrange: Create ticket with this user as creator
        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created Ticket',
            'created_by' => $this->user->id,
        ]);

        // Act
        $createdTicketsCount = $this->user->createdTickets()->count();

        // Assert
        $this->assertEquals(1, $createdTicketsCount);
    }

    public function testUserWithMultipleCreatedTicketsHasCorrectCount(): void
    {
        // Arrange: Create multiple tickets with this user as creator
        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 3',
            'created_by' => $this->user->id,
        ]);

        // Act
        $createdTicketsCount = $this->user->createdTickets()->count();

        // Assert
        $this->assertEquals(3, $createdTicketsCount);
    }

    public function testCreatedTicketsCountUsingWithCount(): void
    {
        // Arrange: Create tickets
        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        // Act: Use withCount
        $user = User::withCount('createdTickets')->find($this->user->id);

        // Assert
        $this->assertEquals(2, $user->created_tickets_count);
    }

    public function testCreatedTicketsCountIncreasesWhenNewTicketCreated(): void
    {
        // Arrange: Initial state with one ticket
        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 1',
            'created_by' => $this->user->id,
        ]);

        $initialCount = $this->user->createdTickets()->count();

        // Act: Create new ticket
        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Ticket 2',
            'created_by' => $this->user->id,
        ]);

        // Assert
        $this->user->refresh();
        $this->assertEquals($initialCount + 1, $this->user->createdTickets()->count());
    }

    public function testCreatedTicketsCountDoesNotIncludeOtherUsersTickets(): void
    {
        // Arrange: Create tickets by different users
        $otherUser = User::factory()->create();

        // This user creates 2 tickets
        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'My Ticket 1',
            'created_by' => $this->user->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'My Ticket 2',
            'created_by' => $this->user->id,
        ]);

        // Other user creates 3 tickets
        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Other Ticket 1',
            'created_by' => $otherUser->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Other Ticket 2',
            'created_by' => $otherUser->id,
        ]);

        // Act
        $userCreatedCount = $this->user->createdTickets()->count();
        $otherUserCreatedCount = $otherUser->createdTickets()->count();

        // Assert
        $this->assertEquals(2, $userCreatedCount);
        $this->assertEquals(2, $otherUserCreatedCount);
    }

    // ============================================
    // COMBINED STATISTICS TESTS
    // ============================================

    public function testUserStatisticsWithAllCountersAtZero(): void
    {
        // Arrange: User with no projects, no assigned tickets, no created tickets
        $user = User::factory()->create();

        // Act
        $user = User::withCount(['projects', 'assignedTickets', 'createdTickets'])->find($user->id);

        // Assert
        $this->assertEquals(0, $user->projects_count);
        $this->assertEquals(0, $user->assigned_tickets_count);
        $this->assertEquals(0, $user->created_tickets_count);
    }

    public function testUserStatisticsWithAllCountersPopulated(): void
    {
        // Arrange: Set up user with projects, assigned tickets, and created tickets
        $project1 = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = Project::create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $this->user->projects()->attach([$project1->id, $project2->id]);

        // Create tickets assigned to user (but created by someone else)
        $otherUser = User::factory()->create();
        $assignedTicket1 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Assigned 1',
            'created_by' => $otherUser->id,
        ]);

        $assignedTicket2 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Assigned 2',
            'created_by' => $otherUser->id,
        ]);

        $this->user->assignedTickets()->attach([$assignedTicket1->id, $assignedTicket2->id]);

        // Create tickets created by user
        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created 1',
            'created_by' => $this->user->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created 2',
            'created_by' => $this->user->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created 3',
            'created_by' => $this->user->id,
        ]);

        // Act
        $user = User::withCount(['projects', 'assignedTickets', 'createdTickets'])->find($this->user->id);

        // Assert
        $this->assertEquals(2, $user->projects_count);
        $this->assertEquals(2, $user->assigned_tickets_count);
        $this->assertEquals(3, $user->created_tickets_count);
    }

    public function testUserCanBeAssignedToTicketTheyCreated(): void
    {
        // Arrange: User creates a ticket and is also assigned to it
        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'My Ticket',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket->id);

        // Act
        $user = User::withCount(['assignedTickets', 'createdTickets'])->find($this->user->id);

        // Assert: Both counts should be 1
        $this->assertEquals(1, $user->assigned_tickets_count);
        $this->assertEquals(1, $user->created_tickets_count);
    }

    public function testStatisticsAreIndependentAcrossMultipleUsers(): void
    {
        // Arrange: Create multiple users with different statistics
        $user1 = User::factory()->create(['name' => 'User 1']);
        $user2 = User::factory()->create(['name' => 'User 2']);
        $user3 = User::factory()->create(['name' => 'User 3']);

        $project1 = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = Project::create(['name' => 'P2', 'ticket_prefix' => 'P2']);
        $project3 = Project::create(['name' => 'P3', 'ticket_prefix' => 'P3']);

        // User 1: 1 project
        $user1->projects()->attach($project1->id);

        // User 2: 2 projects
        $user2->projects()->attach([$project1->id, $project2->id]);

        // User 3: 3 projects
        $user3->projects()->attach([$project1->id, $project2->id, $project3->id]);

        // Create tickets
        $ticket1 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $user1->id,
        ]);

        $ticket2 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T2',
            'created_by' => $user2->id,
        ]);

        $ticket3 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T3',
            'created_by' => $user2->id,
        ]);

        // Act: Get statistics for all users
        $users = User::withCount(['projects', 'createdTickets'])
            ->whereIn('id', [$user1->id, $user2->id, $user3->id])
            ->get();

        // Assert
        $user1Stats = $users->firstWhere('id', $user1->id);
        $user2Stats = $users->firstWhere('id', $user2->id);
        $user3Stats = $users->firstWhere('id', $user3->id);

        $this->assertEquals(1, $user1Stats->projects_count);
        $this->assertEquals(1, $user1Stats->created_tickets_count);

        $this->assertEquals(2, $user2Stats->projects_count);
        $this->assertEquals(2, $user2Stats->created_tickets_count);

        $this->assertEquals(3, $user3Stats->projects_count);
        $this->assertEquals(0, $user3Stats->created_tickets_count);
    }

    // ============================================
    // STATISTICS ACCURACY TESTS
    // ============================================

    public function testProjectsCountMatchesActualProjectMembership(): void
    {
        // Arrange: Add user to projects via pivot table
        $projects = collect([
            Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']),
            Project::create(['name' => 'P2', 'ticket_prefix' => 'P2']),
            Project::create(['name' => 'P3', 'ticket_prefix' => 'P3']),
        ]);

        $this->user->projects()->attach($projects->pluck('id')->toArray());

        // Act: Count via relationship vs withCount
        $relationshipCount = $this->user->projects()->count();
        $user = User::withCount('projects')->find($this->user->id);

        // Assert: Both methods should give same result
        $this->assertEquals(3, $relationshipCount);
        $this->assertEquals(3, $user->projects_count);
        $this->assertEquals($relationshipCount, $user->projects_count);
    }

    public function testAssignedTicketsCountMatchesActualAssignments(): void
    {
        // Arrange: Create and assign tickets
        $tickets = collect([
            Ticket::create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => 'T1',
                'created_by' => $this->user->id,
            ]),
            Ticket::create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => 'T2',
                'created_by' => $this->user->id,
            ]),
            Ticket::create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => 'T3',
                'created_by' => $this->user->id,
            ]),
        ]);

        $this->user->assignedTickets()->attach($tickets->pluck('id')->toArray());

        // Act: Count via relationship vs withCount
        $relationshipCount = $this->user->assignedTickets()->count();
        $user = User::withCount('assignedTickets')->find($this->user->id);

        // Assert: Both methods should give same result
        $this->assertEquals(3, $relationshipCount);
        $this->assertEquals(3, $user->assigned_tickets_count);
        $this->assertEquals($relationshipCount, $user->assigned_tickets_count);
    }

    public function testCreatedTicketsCountMatchesActualCreations(): void
    {
        // Arrange: Create tickets
        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $this->user->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T2',
            'created_by' => $this->user->id,
        ]);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T3',
            'created_by' => $this->user->id,
        ]);

        // Act: Count via relationship vs withCount
        $relationshipCount = $this->user->createdTickets()->count();
        $user = User::withCount('createdTickets')->find($this->user->id);

        // Assert: Both methods should give same result
        $this->assertEquals(3, $relationshipCount);
        $this->assertEquals(3, $user->created_tickets_count);
        $this->assertEquals($relationshipCount, $user->created_tickets_count);
    }

    // ============================================
    // EDGE CASES & BOUNDARY TESTS
    // ============================================

    public function testStatisticsWithLargeNumberOfItems(): void
    {
        // Arrange: Create many items
        $projectIds = [];
        for ($i = 0; $i < 50; $i++) {
            $project = Project::create([
                'name' => "Project $i",
                'ticket_prefix' => "P$i",
            ]);
            $projectIds[] = $project->id;
        }

        $this->user->projects()->attach($projectIds);

        // Create many tickets
        for ($i = 0; $i < 100; $i++) {
            Ticket::create([
                'project_id' => $this->project->id,
                'ticket_status_id' => $this->ticketStatus->id,
                'priority_id' => $this->ticketPriority->id,
                'name' => "Ticket $i",
                'created_by' => $this->user->id,
            ]);
        }

        // Act
        $user = User::withCount(['projects', 'createdTickets'])->find($this->user->id);

        // Assert
        $this->assertEquals(50, $user->projects_count);
        $this->assertEquals(100, $user->created_tickets_count);
    }

    public function testStatisticsAfterProjectDeletion(): void
    {
        // Arrange: User in 2 projects
        $project1 = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = Project::create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $this->user->projects()->attach([$project1->id, $project2->id]);
        $this->assertEquals(2, $this->user->projects()->count());

        // Act: Delete one project
        $project1->delete();

        // Assert: Count should decrease
        $this->user->refresh();
        $this->assertEquals(1, $this->user->projects()->count());
    }

    public function testStatisticsAfterTicketDeletion(): void
    {
        // Arrange: User created 3 tickets
        $ticket1 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $this->user->id,
        ]);

        $ticket2 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T2',
            'created_by' => $this->user->id,
        ]);

        $ticket3 = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T3',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(3, $this->user->createdTickets()->count());

        // Act: Delete one ticket
        $ticket1->delete();

        // Assert: Count should decrease
        $this->user->refresh();
        $this->assertEquals(2, $this->user->createdTickets()->count());
    }

    // ============================================
    // QUERY PERFORMANCE TESTS
    // ============================================

    public function testMultipleStatisticsCanBeLoadedInSingleQuery(): void
    {
        // Arrange: Set up user with data
        $project = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $this->user->projects()->attach($project->id);

        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket->id);

        // Act: Load all statistics in one query
        $user = User::withCount([
            'projects',
            'assignedTickets',
            'createdTickets',
        ])->find($this->user->id);

        // Assert: All counts should be available
        $this->assertNotNull($user->projects_count);
        $this->assertNotNull($user->assigned_tickets_count);
        $this->assertNotNull($user->created_tickets_count);

        $this->assertEquals(1, $user->projects_count);
        $this->assertEquals(1, $user->assigned_tickets_count);
        $this->assertEquals(1, $user->created_tickets_count);
    }

    public function testStatisticsWithCustomAliasesUsingWithCount(): void
    {
        // Arrange
        $project = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $this->user->projects()->attach($project->id);

        Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'T1',
            'created_by' => $this->user->id,
        ]);

        // Act: Use custom aliases like in UserStatisticsChart
        $user = User::withCount([
            'projects as total_projects',
            'assignedTickets as total_assigned_tickets',
            'createdTickets as total_created_tickets',
        ])->find($this->user->id);

        // Assert: Custom aliases should work
        $this->assertEquals(1, $user->total_projects);
        $this->assertEquals(0, $user->total_assigned_tickets);
        $this->assertEquals(1, $user->total_created_tickets);
    }

    // ============================================
    // RELATIONSHIP INTEGRITY TESTS
    // ============================================

    public function testProjectsRelationshipIsAccessible(): void
    {
        // Arrange
        $project = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $this->user->projects()->attach($project->id);

        // Act
        $projects = $this->user->projects;

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $projects);
        $this->assertCount(1, $projects);
        $this->assertEquals('P1', $projects->first()->name);
    }

    public function testAssignedTicketsRelationshipIsAccessible(): void
    {
        // Arrange
        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Test Ticket',
            'created_by' => $this->user->id,
        ]);

        $this->user->assignedTickets()->attach($ticket->id);

        // Act
        $assignedTickets = $this->user->assignedTickets;

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $assignedTickets);
        $this->assertCount(1, $assignedTickets);
        $this->assertEquals('Test Ticket', $assignedTickets->first()->name);
    }

    public function testCreatedTicketsRelationshipIsAccessible(): void
    {
        // Arrange
        $ticket = Ticket::create([
            'project_id' => $this->project->id,
            'ticket_status_id' => $this->ticketStatus->id,
            'priority_id' => $this->ticketPriority->id,
            'name' => 'Created Ticket',
            'created_by' => $this->user->id,
        ]);

        // Act
        $createdTickets = $this->user->createdTickets;

        // Assert
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $createdTickets);
        $this->assertCount(1, $createdTickets);
        $this->assertEquals('Created Ticket', $createdTickets->first()->name);
    }

    // ============================================
    // BULK STATISTICS TESTS
    // ============================================

    public function testCanRetrieveStatisticsForMultipleUsersAtOnce(): void
    {
        // Arrange: Create multiple users with different statistics
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $project1 = Project::create(['name' => 'P1', 'ticket_prefix' => 'P1']);
        $project2 = Project::create(['name' => 'P2', 'ticket_prefix' => 'P2']);

        $user1->projects()->attach($project1->id);
        $user2->projects()->attach([$project1->id, $project2->id]);
        $user3->projects()->attach($project2->id);

        // Act: Retrieve all users with statistics in one query
        $users = User::withCount('projects')
            ->whereIn('id', [$user1->id, $user2->id, $user3->id])
            ->get();

        // Assert
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
        // Arrange: Create multiple users with varying statistics
        $users = User::factory()->count(5)->create();

        $project = Project::create(['name' => 'Shared Project', 'ticket_prefix' => 'SHR']);

        foreach ($users as $index => $user) {
            // Add projects based on index
            if ($index % 2 === 0) {
                $user->projects()->attach($project->id);
            }

            // Create tickets based on index
            for ($i = 0; $i < $index; $i++) {
                Ticket::create([
                    'project_id' => $this->project->id,
                    'ticket_status_id' => $this->ticketStatus->id,
                    'priority_id' => $this->ticketPriority->id,
                    'name' => "Ticket $i for user {$user->id}",
                    'created_by' => $user->id,
                ]);
            }
        }

        // Act: Get statistics for all users
        $usersWithStats = User::withCount(['projects', 'createdTickets'])->get();

        // Assert: Should have all users with their statistics
        $this->assertGreaterThanOrEqual(5, $usersWithStats->count());

        foreach ($usersWithStats as $user) {
            $this->assertIsInt($user->projects_count);
            $this->assertIsInt($user->created_tickets_count);
        }
    }
}
