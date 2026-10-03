<?php

namespace Database\Factories;

use App\Models\BuyingSignal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BuyingSignal> */
class BuyingSignalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'prospect_id' => ProspectFactory::new(), 'type' => 'hiring_growth',
            'evidence' => 'The company lists multiple active operations roles.',
            'source_url' => 'https://example.com/jobs', 'strength' => 'high',
        ];
    }
}
