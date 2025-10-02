<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function testCanUpdateUserPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $oldHash = $user->password;

        // Act
        $newPassword = 'new-secure-password';
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Assert
        $user->refresh();
        $this->assertNotEquals($oldHash, $user->password);
        $this->assertTrue(Hash::check($newPassword, $user->password));
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    public function testPasswordUpdateInvalidatesOldPassword(): void
    {
        // Arrange
        $oldPassword = 'old-password';
        $newPassword = 'new-password';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
        ]);

        // Verify old password works initially
        $this->assertTrue(Hash::check($oldPassword, $user->password));

        // Act: Update password
        $user->update([
            'password' => Hash::make($newPassword),
        ]);
        $user->refresh();

        // Assert: Old password no longer works
        $this->assertFalse(Hash::check($oldPassword, $user->password));
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    public function testCanUpdatePasswordMultipleTimes(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('password1'),
        ]);

        // Act & Assert: Multiple password changes
        $passwords = ['password2', 'password3', 'password4'];
        $previousPassword = 'password1';

        foreach ($passwords as $newPassword) {
            $user->update(['password' => Hash::make($newPassword)]);
            $user->refresh();

            // New password works
            $this->assertTrue(Hash::check($newPassword, $user->password));

            // Previous password doesn't work
            $this->assertFalse(Hash::check($previousPassword, $user->password));

            $previousPassword = $newPassword;
        }
    }

    public function testPasswordUpdateDoesNotAffectOtherFields(): void
    {
        // Arrange
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('old-password'),
        ]);

        // Act: Update only password
        $user->update([
            'password' => Hash::make('new-password'),
        ]);

        // Assert: Other fields remain unchanged
        $user->refresh();
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function testPasswordUpdatePreservesUserIdentity(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $originalId = $user->id;
        $originalEmail = $user->email;

        // Act: Update password
        $user->update([
            'password' => Hash::make('new-password'),
        ]);

        // Assert: User identity preserved
        $user->refresh();
        $this->assertEquals($originalId, $user->id);
        $this->assertEquals($originalEmail, $user->email);
    }
}
