<?php

namespace App\Dto\Product;

readonly class ProductVariantDto
{
    public function __construct(
        public int    $id,
        public string $label,
        public float  $price,
        public ?float $stock,
    )
    {}
}
