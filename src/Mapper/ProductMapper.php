<?php

namespace App\Mapper;

use App\Dto\Product\ProductDto;
use App\Entity\Product;
use App\Utils\Translator\UnitTranslator;

class ProductMapper
{
    public static function toDto(Product $product, UnitTranslator $translator): ProductDto
    {
        return new ProductDto(
            id: $product->getId(),
            name: $product->getName(),
            price: $product->getPriceInEuros(),
            unit: $translator->translate($product->getUnit()),
            image: '/uploads/images/' . $product->getImage(),
            stock: $product->hasStock() ? $product->getStock() : null,
            limited: $product->isLimited(),
            discount: $product->isDiscount(),
            discountText: $product->getDiscountText(),
            inter: $product->getInter(),
        );
    }
}
