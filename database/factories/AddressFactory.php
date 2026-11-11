<?php

namespace Database\Factories;

use App\Models\Address;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'street' => $this->faker->streetAddress,
            'barangay' => $this->faker->word,
            'city' => $this->faker->city,
            'province' => $this->faker->state,
            'region' => $this->faker->word,
            'district' => $this->faker->word,
            'zip_code' => $this->faker->postcode,
            'country' => 'Philippines',
            'latitude' => $this->faker->latitude,
            'longitude' => $this->faker->longitude,
        ];
    }
}
