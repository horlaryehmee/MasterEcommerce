<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'description' => $this->faker->sentence(),
            'sort_order' => 0,
            'is_published' => true,
            'is_featured' => false,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_published' => false]);
    }
}
