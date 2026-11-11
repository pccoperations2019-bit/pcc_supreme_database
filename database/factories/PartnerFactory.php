<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'partner_code' => $this->faker->unique()->lexify('PARTNER-???'),
            'name' => $this->faker->company,
            'status' => 'active',
            'center_id' => Center::factory(),

        ];
    }
}
