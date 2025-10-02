<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UserProfileMockingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ============================================
    // PROFILE RETRIEVAL WITH MOCKING
    // ============================================

    public function testCanMockUserProfileRetrieval(): void
    {
        // Arrange
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->id = 1;
        $userMock->name = 'Mocked User';
        $userMock->email = 'mocked@example.com';

        // Act & Assert
        $this->assertEquals('Mocked User', $userMock->name);
        $this->assertEquals('mocked@example.com', $userMock->email);
    }

    public function testCanMockProfileUpdateOperation(): void
    {
        // Arrange
        $userMock = Mockery::mock(User::class)->makePartial();
        $userMock->shouldReceive('update')
            ->with(['name' => 'New Name'])
            ->once()
            ->andReturn(true);

        // Act
        $result = $userMock->update(['name' => 'New Name']);

        // Assert
        $this->assertTrue($result);
    }
}
