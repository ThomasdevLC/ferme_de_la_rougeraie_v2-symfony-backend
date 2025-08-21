<?php

namespace App\Service\Admin;

use App\Dto\Product\ProductAdminDto;
use App\Entity\Product;
use App\Mapper\ProductAdminMapper;
use App\Repository\Admin\ProductRepository;
use App\Utils\Translator\UnitTranslator;

class ProductService
{
    public function __construct(
        private ProductRepository $productRepository,
        private UnitTranslator $unitTranslator,
    ) {}

    /**
     * Mark products as deleted by their IDs.
     *
     * @param int[] $ids
     * @return string[] Names of products that cannot be deleted
     */

    public function markProductsAsDeletedByIds(array $ids): array
    {
        $nonDeletableNames = $this->productRepository->findNonDeletableProductNames($ids);

        $deletableIds = $this->productRepository->findDeletableProductIds($ids);

        if (!empty($deletableIds)) {
            $this->productRepository->softDeleteProductsByIds($deletableIds);
        }

        return $nonDeletableNames;
    }

    /**
     * Gets products by IDs and maps them as ProductAdminDto.
     *
     * @param int[] $ids
     * @return ProductAdminDto[]
     */
    public function getProductAdminDtosByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $products = $this->productRepository->findProductsByIds($ids);

        return array_map(
            fn(Product $product) => ProductAdminMapper::toDto($product, $this->unitTranslator),
            $products
        );
    }

    public function getSoldOutProducts(): array
    {
        return $this->productRepository->findSoldOutProductNames();
    }

}