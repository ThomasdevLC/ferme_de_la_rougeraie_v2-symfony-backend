<?php

namespace App\Mapper;

use App\Dto\Product\BasketItemDto;
use App\Dto\Product\ProductCategoryDto;
use App\Dto\Product\ProductDto;
use App\Dto\Product\ProductVariantDto;
use App\Entity\BasketItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductCategory;
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
            isBasket: $product->isBasket(),
            basketItems: self::basketItemsToDto($product),
            category: self::categoryToDto($product->getCategory()),
        );
    }

    /**
     * Composition lines for a basket (empty for a regular product),
     * ordered by their display position.
     *
     * @return BasketItemDto[]
     */
    private static function basketItemsToDto(Product $product): array
    {
        if (!$product->isBasket()) {
            return [];
        }

        $items = $product->getBasketItems()->toArray();
        usort(
            $items,
            static fn (BasketItem $a, BasketItem $b): int => $a->getPosition() <=> $b->getPosition()
        );

        return array_map(
            static fn (BasketItem $item): BasketItemDto => new BasketItemDto(
                name: $item->getProduct()->getName(),
                quantity: $item->getQuantity(),
            ),
            $items
        );
    }

    private static function categoryToDto(?ProductCategory $category): ?ProductCategoryDto
    {
        return $category !== null
            ? new ProductCategoryDto($category->value, $category->label())
            : null;
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
