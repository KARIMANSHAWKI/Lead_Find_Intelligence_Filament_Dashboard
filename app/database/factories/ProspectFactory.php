<?php

namespace Database\Factories;

use App\Models\AgentRun;
use App\Models\Organization;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Prospect> */
class ProspectFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'agent_run_id' => AgentRun::factory(),
            'organization_id' => fn (array $attributes): int => AgentRun::findOrFail($attributes['agent_run_id'])->organization_id,
            'company_name' => 'ABC Logistics', 'website' => 'https://example.com',
            'source' => null, 'icp_fit' => 'high', 'product_relevance' => 'high',
            'evidence_quality' => 'high', 'why_now' => 'Actively hiring across operations and expanding its team.',
            'score' => null, 'status' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->for($organization)->for(AgentRun::factory()->for($organization));
    }
}
