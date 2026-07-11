<?php

namespace App\Mapper;

use App\Dto\Order\Create\CartItemDto;

class CartItemMapper
{
    /**
     * @param array $items
     * @return CartItemDto[]
     */
    public static function fromArray(array $items): array
    {
        return array_map(function ($item) {
            return new CartItemDto(
                productId: $item['productId'],
                quantity: $item['quantity'],
                variantId: $item['variantId'] ?? null,
            );
        }, $items);
    }
}
