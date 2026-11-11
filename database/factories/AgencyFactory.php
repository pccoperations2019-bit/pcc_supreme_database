<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'agency_code' => $this->faker->unique()->lexify('AGENCY-???'),
            'name' => $this->faker->company,
            'slug' => $this->faker->slug,
            'description' => $this->faker->sentence,
            'total_centers' => 0,
            'total_partners' => 0,
            'total_farmers' => 0,
        ];
    }
}
