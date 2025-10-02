<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileFillableFieldsTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE FILLABLE FIELDS TESTS
    // ============================================

    public function testOnlyFillableFieldsCanBeMassAssigned(): void
    {
        // Arrange & Act
        $user = (new User())->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'google_id' => 'google-123',
            // email_verified_at is NOT fillable, so it should be ignored
        ]);

        // Assert: Fillable fields are set
        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertEquals('google-123', $user->google_id);
    }

    public function testCanGetFillableFields(): void
    {
        // Arrange
        $user = new User();

        // Act
        $fillable = $user->getFillable();

        // Assert
        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('google_id', $fillable);
    }
}
