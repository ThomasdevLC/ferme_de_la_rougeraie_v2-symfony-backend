<?php

namespace App\Service\Store;

use App\Dto\Product\ProductDto;
use App\Entity\Product;
use App\Enum\ProductCategory;
use App\Mapper\ProductMapper;
use App\Repository\Store\ProductStoreRepository;
use App\Utils\Translator\UnitTranslator;

class ProductStoreService
{
    public function __construct(
        private ProductStoreRepository $productRepository,
        private UnitTranslator $unitTranslator,
    )
    {}
    /**
     * Get displayed products.
     *
     * @return ProductDto[]
     */
    public function getAvailableProductsForFront(): array
    {
        $products = $this->productRepository->findDisplayedAvailableProducts();
        $this->sortByCategoryOrder($products);

        return array_map(
            fn(Product $product) => ProductMapper::toDto($product, $this->unitTranslator),
            $products
        );
    }

    /**
     * Order products by the canonical category order (the ProductCategory
     * declaration order); products without a category come last. usort is
     * stable since PHP 8, so the repository order is preserved within a
     * category.
     *
     * @param Product[] $products
     */
    private function sortByCategoryOrder(array &$products): void
    {
        $rankByKey = array_flip(array_map(
            static fn(ProductCategory $category): string => $category->value,
            ProductCategory::cases()
        ));
        $unrankedLast = count($rankByKey);

        usort($products, static function (Product $a, Product $b) use ($rankByKey, $unrankedLast): int {
            $rankA = $a->getCategory() !== null ? $rankByKey[$a->getCategory()->value] : $unrankedLast;
            $rankB = $b->getCategory() !== null ? $rankByKey[$b->getCategory()->value] : $unrankedLast;

            return $rankA <=> $rankB;
        });
    }
}