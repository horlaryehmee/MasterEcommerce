<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title($this->faker->unique()->words(3, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'price' => $this->faker->numberBetween(1000, 500000),
            'compare_at_price' => null,
            'availability' => 'in_stock',
            'is_featured' => false,
            'is_new_arrival' => false,
            'status' => Product::STATUS_PUBLISHED,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => Product::STATUS_DRAFT]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
