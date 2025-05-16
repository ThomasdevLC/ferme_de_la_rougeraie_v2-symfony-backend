<?php
namespace App\Dto\Order\Create;

/**
 * single item in the shopping cart for order creation.
 */
readonly class CartItemDto
{
public function __construct(
public int $productId,
public float  $quantity
) {}
}
