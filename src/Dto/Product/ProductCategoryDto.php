<?php

namespace App\Dto\Product;

readonly class ProductCategoryDto
{
    public function __construct(
        public string $key,
        public string $label,
    )
    {}
}
