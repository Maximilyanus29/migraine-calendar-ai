<?php

namespace Tests\Feature;

use App\Models\User;
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
}
