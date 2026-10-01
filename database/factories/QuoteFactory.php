<?php

declare(strict_types=1);

namespace Focal\Sales\Database\Factories;

use Focal\Sales\Enums\QuoteStatus;
use Focal\Sales\Models\Deal;
use Focal\Sales\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 500, 20000);
        $tax = round($subtotal * 0.08, 2);
        $total = $subtotal + $tax;

        return [
            'deal_id' => Deal::factory(),
            'quote_number' => 'Q-'.now()->format('Y').'-'.strtoupper(Str::random(5)),
            'title' => $this->faker->words(3, true).' Proposal',
            'status' => QuoteStatus::Draft,
            'subtotal' => $subtotal,
            'discount_amount' => 0.00,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'currency' => 'USD',
            'terms' => 'Payment due within 30 days of acceptance.',
            'public_token' => Str::random(40),
            'expires_at' => now()->addDays(30),
        ];
    }
}
