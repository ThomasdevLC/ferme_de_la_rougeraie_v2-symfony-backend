<?php
namespace App\Dto\Order\Display;

use App\Dto\Product\ProductDto;

/**
 *  A single line item in an order for display.
 *
 */
readonly class OrderItemDto
{
    public function __construct(
        public ProductDto $product,
        public float $quantity,
        public float  $unitPrice,
        public float $lineTotal
    ) {}
}
