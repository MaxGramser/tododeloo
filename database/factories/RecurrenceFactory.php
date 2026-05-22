<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Models\Recurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recurrence>
 */
class RecurrenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'priority' => Priority::Normal,
            'rrule' => 'FREQ=DAILY',
            'dtstart' => now()->startOfDay(),
            'until' => null,
            'active' => true,
            'last_generated_on' => null,
        ];
    }

    public function daily(): static
    {
        return $this->state(fn () => ['rrule' => 'FREQ=DAILY']);
    }

    public function weekdays(): static
    {
        return $this->state(fn () => ['rrule' => 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
