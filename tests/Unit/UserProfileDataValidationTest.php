<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileDataValidationTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE DATA VALIDATION TESTS
    // ============================================

    public function testUserProfileHasRequiredFields(): void
    {
        // Act & Assert: Missing name should throw exception
        $this->expectException(\Illuminate\Database\QueryException::class);

        (new User())->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function testEmailMustBeUniqueAcrossUsers(): void
    {
        // Arrange
        User::factory()->create(['email' => 'unique@example.com']);

        // Act & Assert
        $this->expectException(\Illuminate\Database\QueryException::class);

        (new User())->create([
            'name' => 'Another User',
            'email' => 'unique@example.com',
            'password' => bcrypt('password'),
        ]);
    }
}
