<?php

declare(strict_types=1);

namespace Odden\Sales\Database\Factories;

use Odden\Sales\Models\Deal;
use Odden\Sales\Models\DealProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DealProduct>
 */
class DealProductFactory extends Factory
{
    protected $model = DealProduct::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitPrice = $this->faker->randomFloat(2, 50, 2000);
        $discount = $this->faker->randomElement([0, 0, 5, 10, 15]);

        return [
            'deal_id' => Deal::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => strtoupper($this->faker->bothify('SKU-###-???')),
            'description' => $this->faker->sentence(),
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'discount_percent' => $discount,
            'total_price' => round(($quantity * $unitPrice) * (1 - ($discount / 100)), 2),
            'sort_order' => 0,
        ];
    }
}
