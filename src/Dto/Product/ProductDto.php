<?php

namespace App\Dto\Product;

readonly class ProductDto
{
    /**
     * @param ProductVariantDto[] $variants
     * @param BasketItemDto[]     $basketItems
     */
    public function __construct(
        public int      $id,
        public string   $name,
        public ?float   $price,
        public string   $unit,
        public string   $image,
        public bool     $hasStock,
        public ?float   $stock,
        public bool     $limited,
        public bool     $discount,
        public ?string  $discountText,
        public ?float   $inter,
        public bool     $hasVariants,
        public array    $variants,
        public bool     $isBasket = false,
        public array    $basketItems = [],
        public ?ProductCategoryDto $category = null,
    )
    {}
}
