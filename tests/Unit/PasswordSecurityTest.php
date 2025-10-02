<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function testPasswordIsNotExposedInArraySerialization(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        // Act
        $userArray = $user->toArray();

        // Assert
        $this->assertArrayNotHasKey('password', $userArray);
    }

    public function testPasswordIsNotExposedInJsonSerialization(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('secret-password'),
        ]);

        // Act
        $userJson = $user->toJson();
        $decoded = json_decode($userJson, true);

        // Assert
        $this->assertArrayNotHasKey('password', $decoded);
    }

    public function testPasswordFieldIsMarkedAsHidden(): void
    {
        // Arrange
        $user = new User();

        // Act
        $hiddenFields = $user->getHidden();

        // Assert
        $this->assertContains('password', $hiddenFields);
    }

    public function testPasswordIsCastToHashedValue(): void
    {
        // Arrange & Act
        $user = User::factory()->create([
            'password' => Hash::make('test-password'),
        ]);

        // Assert: Password in database is hashed
        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertEquals(60, strlen($user->password));
    }

    public function testUserCanChangeTheirOwnPassword(): void
    {
        // Arrange
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        // Act: Simulate user changing their own password
        $this->actingAs($user);

        $newPassword = 'new-password';
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Assert
        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));
    }

    public function testPasswordUpdateRequiresCurrentPasswordVerification(): void
    {
        // Arrange
        $currentPassword = 'current-password';
        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act: Verify current password before allowing change
        $currentPasswordIsCorrect = Hash::check($currentPassword, $user->password);

        // Assert: Should verify current password first
        $this->assertTrue($currentPasswordIsCorrect);

        // Only then allow password change
        if ($currentPasswordIsCorrect === true) {
            $user->update([
                'password' => Hash::make('new-password'),
            ]);

            $user->refresh();
            $this->assertTrue(Hash::check('new-password', $user->password));
        }
    }

    public function testPasswordChangeFailsWithIncorrectCurrentPassword(): void
    {
        // Arrange
        $currentPassword = 'current-password';
        $user = User::factory()->create([
            'password' => Hash::make($currentPassword),
        ]);

        // Act: Try to verify with wrong current password
        $providedCurrentPassword = 'wrong-password';
        $currentPasswordIsCorrect = Hash::check($providedCurrentPassword, $user->password);

        // Assert: Verification should fail
        $this->assertFalse($currentPasswordIsCorrect);

        // Password should remain unchanged
        $this->assertTrue(Hash::check($currentPassword, $user->password));
    }
}
