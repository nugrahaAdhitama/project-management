<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserFilterRolesTest extends TestCase
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

    public function testFilterUsersBySpecificRole(): void
    {
        $roleModel = new Role();
        $adminRole = $roleModel->create(['name' => 'admin']);
        $memberRole = $roleModel->create(['name' => 'member']);

        $admin1 = User::factory()->create(['name' => 'Admin 1']);
        $admin2 = User::factory()->create(['name' => 'Admin 2']);
        $member1 = User::factory()->create(['name' => 'Member 1']);

        $admin1->assignRole($adminRole);
        $admin2->assignRole($adminRole);
        $member1->assignRole($memberRole);

        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        $this->assertCount(2, $admins);
        $this->assertTrue($admins->contains($admin1));
        $this->assertTrue($admins->contains($admin2));
        $this->assertFalse($admins->contains($member1));
    }

    public function testFilterUsersByMultipleRoles(): void
    {
        $roleModel = new Role();
        $adminRole = $roleModel->create(['name' => 'admin']);
        $managerRole = $roleModel->create(['name' => 'manager']);
        $memberRole = $roleModel->create(['name' => 'member']);

        $admin = User::factory()->create(['name' => 'Admin']);
        $manager = User::factory()->create(['name' => 'Manager']);
        $member = User::factory()->create(['name' => 'Member']);

        $admin->assignRole($adminRole);
        $manager->assignRole($managerRole);
        $member->assignRole($memberRole);

        $privilegedUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['admin', 'manager']);
        })->get();

        $this->assertCount(2, $privilegedUsers);
        $this->assertTrue($privilegedUsers->contains($admin));
        $this->assertTrue($privilegedUsers->contains($manager));
        $this->assertFalse($privilegedUsers->contains($member));
    }

    public function testFilterUsersWithoutRoles(): void
    {
        $roleModel = new Role();
        $role = $roleModel->create(['name' => 'admin']);

        $userWithRole = User::factory()->create(['name' => 'With Role']);
        $userWithoutRole = User::factory()->create(['name' => 'Without Role']);

        $userWithRole->assignRole($role);

        $usersWithoutRoles = User::whereDoesntHave('roles')->get();

        $this->assertTrue($usersWithoutRoles->contains($userWithoutRole));
        $this->assertFalse($usersWithoutRoles->contains($userWithRole));
    }

    public function testFilterUsersWithMultipleRolesAssigned(): void
    {
        $roleModel = new Role();
        $adminRole = $roleModel->create(['name' => 'admin']);
        $managerRole = $roleModel->create(['name' => 'manager']);

        $user = User::factory()->create();
        $user->assignRole([$adminRole, $managerRole]);

        $admins = User::whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->get();

        $this->assertTrue($admins->contains($user));

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('manager'));
    }
}
