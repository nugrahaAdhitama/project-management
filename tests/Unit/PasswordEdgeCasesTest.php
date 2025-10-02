<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    public function testMultipleUsersCanHaveSamePassword(): void
    {
        // Arrange
        $sharedPassword = 'common-password';

        // Act
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'password' => Hash::make($sharedPassword),
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'password' => Hash::make($sharedPassword),
        ]);

        // Assert: Both users can use same password
        $this->assertTrue(Hash::check($sharedPassword, $user1->password));
        $this->assertTrue(Hash::check($sharedPassword, $user2->password));

        // But their hashes are different
        $this->assertNotEquals($user1->password, $user2->password);
    }

    public function testPasswordResetDoesNotAffectOtherUsers(): void
    {
        // Arrange
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'password' => Hash::make('password1'),
        ]);

        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'password' => Hash::make('password2'),
        ]);

        // Act: Reset user1's password
        $user1->update([
            'password' => Hash::make('new-password1'),
        ]);

        // Assert: user2's password unchanged
        $user1->refresh();
        $user2->refresh();

        $this->assertTrue(Hash::check('new-password1', $user1->password));
        $this->assertTrue(Hash::check('password2', $user2->password));
    }

    public function testCanBulkUpdatePasswordsForMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create([
            'password' => Hash::make('old-password'),
        ]);

        $newPassword = 'new-bulk-password';
        $newHashedPassword = Hash::make($newPassword);

        // Act: Bulk update (simulated)
        foreach ($users as $user) {
            $user->update(['password' => $newHashedPassword]);
        }

        // Assert: All users have new password
        foreach ($users->fresh() as $user) {
            $this->assertTrue(Hash::check($newPassword, $user->password));
        }
    }
}
