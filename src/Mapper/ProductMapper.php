<?php

namespace App\Mapper;

use App\Dto\Product\ProductDto;
use App\Dto\Product\ProductVariantDto;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Utils\Translator\UnitTranslator;

class ProductMapper
{
    public static function toDto(Product $product, UnitTranslator $translator): ProductDto
    {
        $variants = [];
        foreach ($product->getVariants() as $variant) {
            if ($variant->isDisplayed()) {
                $variants[] = self::variantToDto($variant);
            }
        }

        return new ProductDto(
            id: $product->getId(),
            name: $product->getName(),
            price: $product->hasVariants() ? null : $product->getPriceInEuros(),
            unit: $translator->translate($product->getUnit()),
            image: '/uploads/images/' . $product->getImage(),
            hasStock:    $product->hasStock(),
            stock: $product->hasStock() ? $product->getStock() : null,
            limited: $product->isLimited(),
            discount: $product->isDiscount(),
            discountText: $product->getDiscountText(),
            inter: $product->getInter(),
            hasVariants: $product->hasVariants(),
            variants: $variants,
        );
    }

    private static function variantToDto(ProductVariant $variant): ProductVariantDto
    {
        return new ProductVariantDto(
            id: $variant->getId(),
            label: $variant->getLabel(),
            price: $variant->getPriceInEuros(),
            stock: $variant->getStock(),
        );
    }
}
