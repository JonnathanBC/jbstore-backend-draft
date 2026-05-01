<?php

namespace App\Modules\Products\Factories;

use App\Modules\Products\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    private static function downloadImage(): string
    {
        $dir = storage_path('app/public/products');

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = \Illuminate\Support\Str::uuid() . '.jpg';
        $path = $dir . '/' . $filename;

        $imageData = file_get_contents('https://picsum.photos/640/480');
        file_put_contents($path, $imageData);

        return 'products/' . $filename;
    }

    public function definition(): array
    {
        return [
            "sku" => $this->faker->unique()->numberBetween(100000, 999999),
            "name" => $this->faker->sentence(),
            "description" => $this->faker->text(200),
            "image_path" => self::downloadImage(),
            "price" => $this->faker->unique()->randomFloat(2, 1, 1000),
            "subcategory_id" => $this->faker->unique()->numberBetween(1, 632),
        ];
    }
}
