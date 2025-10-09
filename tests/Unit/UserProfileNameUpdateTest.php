<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileNameUpdateTest extends TestCase
{
    use RefreshDatabase;

    // ============================================
    // PROFILE UPDATE TESTS - NAME
    // ============================================

    public function testCanUpdateUserProfileName(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'Old Name',
        ]);

        // Act
        $user->update(['name' => 'New Name']);

        // Assert
        $user->refresh();
        $this->assertEquals('New Name', $user->name);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function testCanUpdateUserNameToEmptyString(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
        ]);

        // Act
        $user->update(['name' => '']);

        // Assert
        $user->refresh();
        $this->assertEquals('', $user->name);
    }

    public function testCanUpdateUserNameWithSpecialCharacters(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $specialName = "O'Brien-Smith, Jr.";
        $user->update(['name' => $specialName]);

        // Assert
        $user->refresh();
        $this->assertEquals($specialName, $user->name);
    }

    public function testCanUpdateUserNameWithUnicodeCharacters(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $unicodeName = "张三 李四 Müller Ñoño";
        $user->update(['name' => $unicodeName]);

        // Assert
        $user->refresh();
        $this->assertEquals($unicodeName, $user->name);
    }

    public function testCanHandleVeryLongName(): void
    {
        // Arrange
        $longName = str_repeat('A', 255);
        $user = User::factory()->create();

        // Act
        $user->update(['name' => $longName]);

        // Assert
        $user->refresh();
        $this->assertEquals($longName, $user->name);
    }
}
