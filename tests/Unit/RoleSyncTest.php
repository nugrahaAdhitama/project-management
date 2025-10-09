<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSyncTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdminRole;
    protected $adminRole;
    protected $memberRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles
        $this->superAdminRole = (new Role())->create(['name' => 'super_admin']);
        $this->adminRole = (new Role())->create(['name' => 'admin']);
        $this->memberRole = (new Role())->create(['name' => 'member']);
    }

    // ============================================
    // ROLE SYNC TESTS
    // ============================================

    public function testCanSyncRoles(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['super_admin', 'member']);
        $this->assertCount(2, $user->roles);

        // Act: Sync to only admin role
        $user->syncRoles(['admin']);

        // Assert
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('super_admin'));
        $this->assertFalse($user->hasRole('member'));
        $this->assertCount(1, $user->roles);
    }

    public function testSyncRolesReplacesAllExistingRoles(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->assignRole(['super_admin', 'admin', 'member']);

        // Act
        $user->syncRoles(['member']);

        // Assert: Only member role should remain
        $this->assertCount(1, $user->roles);
        $this->assertTrue($user->hasRole('member'));
    }
}
