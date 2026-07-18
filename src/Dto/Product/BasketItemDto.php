<?php

namespace App\Dto\Product;

readonly class BasketItemDto
{
    public function __construct(
        public string $name,
        public float  $quantity,
        public string $unit,
    )
    {}
}
