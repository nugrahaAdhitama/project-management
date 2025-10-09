<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationBulkOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function testCanBulkVerifyMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->unverified()->count(5)->create();
        
        foreach ($users as $user) {
            $this->assertFalse($user->hasVerifiedEmail());
        }

        // Act
        $verifiedAt = now();
        User::whereIn('id', $users->pluck('id'))->update([
            'email_verified_at' => $verifiedAt,
        ]);

        // Assert
        foreach ($users->fresh() as $user) {
            $this->assertTrue($user->hasVerifiedEmail());
            $this->assertNotNull($user->email_verified_at);
        }
    }

    public function testCanBulkUnverifyMultipleUsers(): void
    {
        // Arrange
        $users = User::factory()->count(5)->create([
            'email_verified_at' => now(),
        ]);

        foreach ($users as $user) {
            $this->assertTrue($user->hasVerifiedEmail());
        }

        // Act
        User::whereIn('id', $users->pluck('id'))->update([
            'email_verified_at' => null,
        ]);

        // Assert
        foreach ($users->fresh() as $user) {
            $this->assertFalse($user->hasVerifiedEmail());
            $this->assertNull($user->email_verified_at);
        }
    }
}
