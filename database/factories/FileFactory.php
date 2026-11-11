<?php

namespace Database\Factories;

use App\Models\File;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = 'files/'.$this->faker->uuid.'.txt';
        Storage::put($filename, $this->faker->text);

        return [
            'name' => $this->faker->word,
            'path' => $filename,
            'type' => 'text/plain',
            'size' => Storage::size($filename),
            'uploaded_by' => 1, // or use a related user factory
        ];
    }
}
