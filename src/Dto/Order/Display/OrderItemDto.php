<?php
namespace App\Dto\Order\Display;

/**
 *  A single line item in an order for display.
 *
 */
readonly class OrderItemDto
{
    public function __construct(
        public string $productName,
        public float $quantity,
        public float  $unitPrice,
        public float $lineTotal
    ) {}
}
