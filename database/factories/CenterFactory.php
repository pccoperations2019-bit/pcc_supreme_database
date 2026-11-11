<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Center;
use Illuminate\Database\Eloquent\Factories\Factory;

class CenterFactory extends Factory
{
    protected $model = Center::class;

    public function definition()
    {
        return [
            'agency_id' => Agency::factory(), // <- automatically creates an agency if not provided
            'center_code' => $this->faker->unique()->bothify('CENTER-###'),
            'name' => $this->faker->company,
            'slug' => $this->faker->slug,
            'description' => $this->faker->sentence,
            'total_partners' => $this->faker->numberBetween(0, 20),
            'total_farmers' => $this->faker->numberBetween(0, 100),
        ];
    }
}
