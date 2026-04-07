<?php

namespace Tests\Feature;

use App\Models\Attack;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttackApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create([
            'email' => 'attacker@example.com',
            'password' => 'password123',
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_guest_cannot_access_attacks(): void
    {
        $this->getJson('/api/v1/attacks?from=2026-01-01&to=2026-01-31')
            ->assertUnauthorized();

        $this->getJson('/api/v1/attacks/last')->assertUnauthorized();
    }

    public function test_index_requires_from_and_to(): void
    {
        $this->actingUser();

        $this->getJson('/api/v1/attacks')
            ->assertUnprocessable();
    }

    public function test_user_can_create_attack(): void
    {
        $user = $this->actingUser();

        $start = CarbonImmutable::now()->subHours(4);
        $end = CarbonImmutable::now()->subHours(2);

        $response = $this->postJson('/api/v1/attacks', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'intensity' => 7,
            'triggers' => ['stress', 'weather'],
            'pain_types' => ['pulsating'],
            'localizations' => ['temples'],
            'symptoms' => ['nausea'],
            'auras' => ['floaters'],
            'medications' => 'Ибупрофен',
            'relief' => true,
            'notes' => 'Заметка',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.intensity', 7)
            ->assertJsonPath('data.triggers', ['stress', 'weather']);

        $this->assertDatabaseHas('attacks', [
            'user_id' => $user->id,
            'intensity' => 7,
        ]);
    }

    public function test_user_can_create_attack_with_null_end_for_ongoing(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('end_at is nullable only after pgsql migration; CI exercises this on PostgreSQL.');
        }

        $user = $this->actingUser();

        $start = CarbonImmutable::now()->subHour();

        $this->postJson('/api/v1/attacks', [
            'start_at' => $start->toIso8601String(),
            'end_at' => null,
            'intensity' => 5,
        ])->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.end_at', null);
    }

    public function test_create_rejects_invalid_option_values(): void
    {
        $this->actingUser();

        $start = CarbonImmutable::now()->subHours(4);
        $end = CarbonImmutable::now()->subHours(2);

        $this->postJson('/api/v1/attacks', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'intensity' => 5,
            'triggers' => ['not_a_real_trigger'],
        ])->assertUnprocessable();
    }

    public function test_index_returns_attacks_overlapping_range(): void
    {
        $user = $this->actingUser();

        // Spans midnight into range
        $cross = Attack::factory()->for($user)->create([
            'start_at' => CarbonImmutable::parse('2026-01-04 22:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-01-05 03:00:00', 'UTC'),
        ]);

        // Long attack still open across the queried day (SQLite test DB has NOT NULL end_at)
        $stillOpen = Attack::factory()->for($user)->create([
            'start_at' => CarbonImmutable::parse('2026-01-03 08:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-01-10 20:00:00', 'UTC'),
        ]);

        $before = Attack::factory()->for($user)->create([
            'start_at' => CarbonImmutable::parse('2026-01-02 10:00:00', 'UTC'),
            'end_at' => CarbonImmutable::parse('2026-01-02 11:00:00', 'UTC'),
        ]);

        $response = $this->getJson('/api/v1/attacks?from=2026-01-05&to=2026-01-05');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($cross->id, $ids);
        $this->assertContains($stillOpen->id, $ids);
        $this->assertNotContains($before->id, $ids);
    }

    public function test_last_returns_most_recent_by_start_at(): void
    {
        $user = $this->actingUser();

        Attack::factory()->for($user)->create([
            'start_at' => CarbonImmutable::now()->subDays(5),
            'end_at' => CarbonImmutable::now()->subDays(5)->addHour(),
        ]);

        $latest = Attack::factory()->for($user)->create([
            'start_at' => CarbonImmutable::now()->subDay(),
            'end_at' => CarbonImmutable::now()->subDay()->addHour(),
        ]);

        $this->getJson('/api/v1/attacks/last')
            ->assertOk()
            ->assertJsonPath('data.id', $latest->id);
    }

    public function test_last_returns_empty_object_when_no_attacks(): void
    {
        $this->actingUser();

        $this->getJson('/api/v1/attacks/last')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_show_returns_404_for_other_users_attack(): void
    {
        $this->actingUser();

        $otherAttack = Attack::factory()->create();

        $this->getJson('/api/v1/attacks/'.$otherAttack->id)
            ->assertNotFound();
    }

    public function test_user_can_update_own_attack(): void
    {
        $user = $this->actingUser();

        $attack = Attack::factory()->for($user)->create([
            'intensity' => 3,
        ]);

        $newStart = CarbonImmutable::now()->subHours(6);
        $newEnd = CarbonImmutable::now()->subHours(4);

        $this->putJson('/api/v1/attacks/'.$attack->id, [
            'start_at' => $newStart->toIso8601String(),
            'end_at' => $newEnd->toIso8601String(),
            'intensity' => 9,
        ])->assertOk()
            ->assertJsonPath('data.intensity', 9);

        $this->assertSame(9, $attack->fresh()->intensity);
    }

    public function test_user_cannot_update_other_users_attack(): void
    {
        $this->actingUser();
        $attack = Attack::factory()->create();

        $start = CarbonImmutable::now()->subHours(6);
        $end = CarbonImmutable::now()->subHours(4);

        $this->putJson('/api/v1/attacks/'.$attack->id, [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'intensity' => 9,
        ])->assertNotFound();
    }

    public function test_user_can_delete_own_attack(): void
    {
        $user = $this->actingUser();
        $attack = Attack::factory()->for($user)->create();

        $this->deleteJson('/api/v1/attacks/'.$attack->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('attacks', ['id' => $attack->id]);
    }

    public function test_delete_for_foreign_attack_returns_not_deleted(): void
    {
        $this->actingUser();
        $attack = Attack::factory()->create();

        $this->deleteJson('/api/v1/attacks/'.$attack->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', false);

        $this->assertDatabaseHas('attacks', ['id' => $attack->id]);
    }
}
