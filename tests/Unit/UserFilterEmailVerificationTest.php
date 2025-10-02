<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFilterEmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected Project $project;
    protected TicketStatus $ticketStatus;
    protected TicketPriority $ticketPriority;

    protected function setUp(): void
    {
        parent::setUp();

        $projectModel = new Project();
        $this->project = $projectModel->create([
            'name' => 'Test Project',
            'description' => 'Test Description',
            'ticket_prefix' => 'TEST',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $statusModel = new TicketStatus();
        $this->ticketStatus = $statusModel->create([
            'name' => 'Open',
            'color' => '#3B82F6',
            'sort_order' => 1,
            'is_completed' => false,
            'project_id' => $this->project->id,
        ]);

        $priorityModel = new TicketPriority();
        $this->ticketPriority = $priorityModel->firstOrCreate(
            ['name' => 'Medium'],
            ['color' => '#F59E0B']
        );
    }

    public function testFilterUsersWithUnverifiedEmail(): void
    {
        $verifiedUser = User::factory()->create([
            'name' => 'Verified User',
            'email_verified_at' => now(),
        ]);

        $unverifiedUser = User::factory()->create([
            'name' => 'Unverified User',
            'email_verified_at' => null,
        ]);

        $unverifiedUsers = User::whereNull('email_verified_at')->get();

        $this->assertCount(1, $unverifiedUsers);
        $this->assertTrue($unverifiedUsers->contains($unverifiedUser));
        $this->assertFalse($unverifiedUsers->contains($verifiedUser));
    }

    public function testFilterUsersWithVerifiedEmail(): void
    {
        $verifiedUser = User::factory()->create([
            'name' => 'Verified User',
            'email_verified_at' => now(),
        ]);

        $unverifiedUser = User::factory()->create([
            'name' => 'Unverified User',
            'email_verified_at' => null,
        ]);

        $verifiedUsers = User::whereNotNull('email_verified_at')->get();

        $this->assertCount(1, $verifiedUsers);
        $this->assertTrue($verifiedUsers->contains($verifiedUser));
        $this->assertFalse($verifiedUsers->contains($unverifiedUser));
    }

    public function testFilterUnverifiedEmailsReturnsCorrectCount(): void
    {
        User::factory()->count(5)->create([
            'email_verified_at' => null,
        ]);

        User::factory()->count(3)->create([
            'email_verified_at' => now(),
        ]);

        $unverifiedUsers = User::whereNull('email_verified_at')->get();

        $this->assertCount(5, $unverifiedUsers);
    }

    public function testFilterEmailVerificationWithSpecificDate(): void
    {
        $recentlyVerified = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $oldVerified = User::factory()->create([
            'email_verified_at' => now()->subMonths(6),
        ]);

        $unverified = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $recentlyVerifiedUsers = User::whereNotNull('email_verified_at')
            ->where('email_verified_at', '>=', now()->subDays(30))
            ->get();

        $this->assertCount(1, $recentlyVerifiedUsers);
        $this->assertTrue($recentlyVerifiedUsers->contains($recentlyVerified));
        $this->assertFalse($recentlyVerifiedUsers->contains($oldVerified));
        $this->assertFalse($recentlyVerifiedUsers->contains($unverified));
    }
}
