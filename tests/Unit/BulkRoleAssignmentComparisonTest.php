<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkRoleAssignmentComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;
    protected $developerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $permissionModel = new Permission();
        $permissionModel->create(['name' => 'view_user']);

        $roleModel = new Role();
        $this->superAdminRole = $roleModel->create(['name' => 'super_admin']);
        $this->adminRole = $roleModel->create(['name' => 'admin']);
        $this->memberRole = $roleModel->create(['name' => 'member']);
        $this->developerRole = $roleModel->create(['name' => 'developer']);
    }

    public function testCompareReplaceModeVsAddMode(): void
    {
        // Arrange: Create two users with same initial role
        $userForReplace = User::factory()->create();
        $userForReplace->assignRole(['member', 'developer']);

        $userForAdd = User::factory()->create();
        $userForAdd->assignRole(['member', 'developer']);

        // Verify initial state
        $this->assertCount(2, $userForReplace->roles);
        $this->assertCount(2, $userForAdd->roles);

        // Act: Apply different modes
        // Replace mode
        $userForReplace->roles()->sync([$this->adminRole->id]);

        // Add mode
        $userForAdd->roles()->syncWithoutDetaching([$this->adminRole->id]);

        // Assert: Different results
        $userForReplace->refresh();
        $this->assertTrue($userForReplace->hasRole('admin'));
        $this->assertFalse($userForReplace->hasRole('member'));
        $this->assertFalse($userForReplace->hasRole('developer'));
        $this->assertCount(1, $userForReplace->roles);

        $userForAdd->refresh();
        $this->assertTrue($userForAdd->hasRole('admin'));
        $this->assertTrue($userForAdd->hasRole('member'));
        $this->assertTrue($userForAdd->hasRole('developer'));
        $this->assertCount(3, $userForAdd->roles);
    }

    public function testBulkOperationMimicsFilamentResourceLogic(): void
    {
        // This test mimics the exact logic from UserResource.php bulk action

        // Arrange
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            $user->assignRole('member');
        }

        // Simulate Filament bulk action data
        $data = [
            'roles' => [$this->adminRole->id, $this->developerRole->id],
            'role_mode' => 'add'
        ];

        // Act: Execute the same logic as in UserResource.php
        foreach ($users as $record) {
            if ($data['role_mode'] === 'replace') {
                $record->roles()->sync($data['roles']);
            } else {
                $record->roles()->syncWithoutDetaching($data['roles']);
            }
        }

        // Assert
        foreach ($users as $user) {
            $user->refresh();
            $this->assertTrue($user->hasRole('member')); // original
            $this->assertTrue($user->hasRole('admin')); // added
            $this->assertTrue($user->hasRole('developer')); // added
            $this->assertCount(3, $user->roles);
        }
    }
}