<?php

namespace Database\Factories;

use App\Models\Upload;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Upload>
 */
class UploadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['jpg', 'pdf', 'txt', 'csv']);
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
        ];
        $disk = (string) config('filesystems.default', 'local');

        return [
            'user_id' => User::factory(),
            'name' => fake()->slug(3).'.'.$extension,
            'disk' => $disk,
            'driver' => (string) config("filesystems.disks.{$disk}.driver", $disk),
            'path' => 'uploads/'.fake()->uuid().'.'.$extension,
            'extension' => $extension,
            'size' => fake()->numberBetween(1024, 10 * 1024 * 1024),
            'mime_type' => $mimeTypes[$extension],
        ];
    }
}
