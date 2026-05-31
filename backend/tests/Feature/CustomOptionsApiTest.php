<?php

namespace Tests\Feature;

use App\Models\CustomTrigger;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomOptionsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_custom_option_for_category(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/custom-options', [
            'category' => 'symptoms',
            'name' => 'Озноб',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.category', 'symptoms')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('custom_triggers', [
            'user_id' => $user->id,
            'category' => 'symptoms',
            'name' => 'Озноб',
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_custom_option_returns_existing_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $existing = CustomTrigger::create([
            'user_id' => $user->id,
            'category' => 'symptoms',
            'name' => 'Озноб',
            'name_normalized' => 'озноб',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/custom-options', [
            'category' => 'symptoms',
            'name' => '  озноб  ',
        ])->assertOk()
            ->assertJsonPath('data.id', $existing->id);

        $this->assertSame(1, CustomTrigger::query()->where('user_id', $user->id)->count());
    }

    public function test_custom_option_creation_respects_category_limit(): void
    {
        config(['migraine.custom_options_max_per_category' => 2]);

        $user = User::factory()->create();
        $this->actingAs($user);

        CustomTrigger::create([
            'user_id' => $user->id,
            'category' => 'triggers',
            'name' => 'Первый',
            'name_normalized' => 'первый',
            'status' => 'pending',
        ]);
        CustomTrigger::create([
            'user_id' => $user->id,
            'category' => 'triggers',
            'name' => 'Второй',
            'name_normalized' => 'второй',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/custom-options', [
            'category' => 'triggers',
            'name' => 'Третий',
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'Достигнут лимит своих вариантов (2 на категорию)');
    }

    public function test_user_can_create_attack_with_pending_custom_option(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $custom = CustomTrigger::create([
            'user_id' => $user->id,
            'category' => 'triggers',
            'name' => 'Сильный запах',
            'name_normalized' => 'сильный запах',
            'status' => 'pending',
        ]);

        $start = CarbonImmutable::now()->subHours(2);
        $end = CarbonImmutable::now()->subHour();

        $this->postJson('/api/v1/attacks', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'intensity' => 6,
            'triggers' => ['custom:'.$custom->id],
        ])->assertCreated()
            ->assertJsonPath('data.triggers', ['custom:'.$custom->id]);
    }

    public function test_user_can_create_attack_with_local_client_option(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $start = CarbonImmutable::now()->subHours(2);
        $end = CarbonImmutable::now()->subHour();

        $this->postJson('/api/v1/attacks', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'intensity' => 6,
            'triggers' => ['local:abcd1234-5678-90ab-cdef'],
        ])->assertCreated()
            ->assertJsonPath('data.triggers', ['local:abcd1234-5678-90ab-cdef']);
    }

    public function test_create_attack_rejects_invalid_local_option_key(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $start = CarbonImmutable::now()->subHours(2);
        $end = CarbonImmutable::now()->subHour();

        $this->postJson('/api/v1/attacks', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'intensity' => 6,
            'triggers' => ['local:short'],
        ])->assertUnprocessable();
    }

    public function test_create_attack_rejects_rejected_custom_option(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $custom = CustomTrigger::create([
            'user_id' => $user->id,
            'category' => 'triggers',
            'name' => 'Отклонённый',
            'name_normalized' => 'отклонённый',
            'status' => 'rejected',
        ]);

        $start = CarbonImmutable::now()->subHours(2);
        $end = CarbonImmutable::now()->subHour();

        $this->postJson('/api/v1/attacks', [
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'intensity' => 6,
            'triggers' => ['custom:'.$custom->id],
        ])->assertUnprocessable();
    }
}
