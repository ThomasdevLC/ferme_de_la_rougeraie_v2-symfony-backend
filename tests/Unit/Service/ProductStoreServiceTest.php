<?php

namespace App\Tests\Unit\Service;

use App\Entity\Product;
use App\Enum\ProductCategory;
use App\Enum\ProductUnit;
use App\Repository\Store\ProductStoreRepository;
use App\Service\Store\ProductStoreService;
use App\Utils\Translator\UnitTranslator;
use PHPUnit\Framework\TestCase;

class ProductStoreServiceTest extends TestCase
{
    public function testProductsAreOrderedByCanonicalCategoryOrder(): void
    {
        // Repository returns products in an arbitrary order; two categories are
        // interleaved and one product has no category.
        $repositoryOrder = [
            $this->makeProduct(1, 'Melon', ProductCategory::FRUIT),
            $this->makeProduct(2, 'Tomate', ProductCategory::VEGETABLE),
            $this->makeProduct(3, 'Divers', null),
            $this->makeProduct(4, 'Pomme', ProductCategory::FRUIT),
            $this->makeProduct(5, 'Courgette', ProductCategory::VEGETABLE),
        ];

        $dtos = $this->makeService($repositoryOrder)->getAvailableProductsForFront();

        // VEGETABLE before FRUIT (declaration order), null last; within a
        // category the repository order is preserved (stable sort).
        $this->assertSame(
            ['Tomate', 'Courgette', 'Melon', 'Pomme', 'Divers'],
            array_map(static fn($dto) => $dto->name, $dtos),
        );
    }

    /**
     * @param Product[] $products
     */
    private function makeService(array $products): ProductStoreService
    {
        $repository = $this->createMock(ProductStoreRepository::class);
        $repository->method('findDisplayedAvailableProducts')->willReturn($products);

        return new ProductStoreService($repository, new UnitTranslator());
    }

    private function makeProduct(int $id, string $name, ?ProductCategory $category): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setPriceInEuros(1.0)
            ->setUnit(ProductUnit::PIECE)
            ->setImage($name . '.jpg')
            ->setCategory($category);

        $ref = new \ReflectionProperty($product, 'id');
        $ref->setAccessible(true);
        $ref->setValue($product, $id);

        return $product;
    }
}
