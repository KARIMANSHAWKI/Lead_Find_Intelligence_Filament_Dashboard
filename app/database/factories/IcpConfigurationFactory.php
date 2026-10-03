<?php

namespace Database\Factories;

use App\Models\IcpConfiguration;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IcpConfiguration>
 */
class IcpConfigurationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'product' => 'Recruitment Management Software',
            'target_industries' => ['Software', 'Logistics'],
            'location' => 'Egypt',
            'company_size_min' => 50,
            'company_size_max' => 300,
            'ideal_customer_description' => 'Companies actively growing their teams.',
        ];
    }
}
