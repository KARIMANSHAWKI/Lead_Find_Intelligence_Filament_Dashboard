<?php

namespace Database\Factories;

use App\Models\AgentRun;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AgentRun> */
class AgentRunFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(), 'status' => 'pending',
            'started_at' => null, 'completed_at' => null, 'failed_at' => null,
            'error_message' => null, 'candidates_found' => 0, 'prospects_qualified' => 0,
        ];
    }

    public function running(): static
    {
        return $this->state(['status' => 'running', 'started_at' => '2026-01-15 10:00:00']);
    }

    public function completed(): static
    {
        return $this->running()->state([
            'status' => 'completed', 'completed_at' => '2026-01-15 10:05:00',
            'candidates_found' => 16, 'prospects_qualified' => 5,
        ]);
    }

    public function failed(): static
    {
        return $this->running()->state([
            'status' => 'failed', 'failed_at' => '2026-01-15 10:05:00',
            'error_message' => 'Lead Intelligence service is temporarily unavailable.',
        ]);
    }
}
