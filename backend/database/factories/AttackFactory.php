<?php

namespace Database\Factories;

use App\Models\Attack;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attack>
 */
class AttackFactory extends Factory
{
    protected $model = Attack::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subDays(random_int(2, 20))->subHours(random_int(0, 5));
        $end = (clone $start)->addMinutes(random_int(30, 300));

        return [
            'user_id' => User::factory(),
            'start_at' => $start,
            'end_at' => $end,
            'intensity' => random_int(1, 10),
            'medications' => null,
            'relief' => null,
            'pain_types' => [],
            'localizations' => [],
            'triggers' => [],
            'symptoms' => [],
            'auras' => [],
            'notes' => null,
        ];
    }
}
