<?php

namespace App\Tests\Unit\Service;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\Admin\ProductRepository;
use App\Repository\Admin\ProductVariantRepository;
use App\Service\Store\StockStoreService;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use PHPUnit\Framework\TestCase;

class StockStoreServiceTest extends TestCase
{
    private function buildProduct(int $stock): Product
    {
        $product = new Product();
        $product->setHasStock(true);
        $product->setStock($stock);
        $product->setIsDisplayed(true);
        $product->setName('Tomate');

        return $product;
    }

    public function testCheckAndDecreaseStockDecreasesStockWhenSufficient(): void
    {
        $product = $this->buildProduct(10);

        $repo = $this->createMock(ProductRepository::class);
        $repo->method('find')->willReturn($product);

        $variantRepo = $this->createMock(ProductVariantRepository::class);
        $em      = $this->createMock(EntityManagerInterface::class);
        $service = new StockStoreService($repo, $variantRepo, $em);

        $result = $service->checkAndDecreaseStock(1, 3);

        $this->assertSame($product, $result);
        $this->assertEquals(7, $product->getStock());
    }

    public function testCheckAndDecreaseStockDoesNotDecrementWhenHasStockFalse(): void
    {
        $product = new Product();
        $product->setHasStock(false);
        $product->setStock(10);
        $product->setIsDisplayed(true);
        $product->setName('Persil');

        $repo = $this->createMock(ProductRepository::class);
        $repo->method('find')->willReturn($product);

        $variantRepo = $this->createMock(ProductVariantRepository::class);
        $em      = $this->createMock(EntityManagerInterface::class);
        $service = new StockStoreService($repo, $variantRepo, $em);

        $result = $service->checkAndDecreaseStock(1, 5);

        $this->assertSame($product, $result);
        $this->assertEquals(10, $product->getStock()); // stock inchangé
    }

    public function testCheckAndDecreaseStockThrowsWhenStockInsufficient(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Stock insuffisant/');

        $product = $this->buildProduct(2);

        $repo = $this->createMock(ProductRepository::class);
        $repo->method('find')->willReturn($product);

        $variantRepo = $this->createMock(ProductVariantRepository::class);
        $em      = $this->createMock(EntityManagerInterface::class);
        $service = new StockStoreService($repo, $variantRepo, $em);

        $service->checkAndDecreaseStock(1, 5);
    }

    /**
     * @dataProvider provideInvalidQuantities
     */
    public function testInvalidQuantity(float $quantity): void
    {
        $repo = $this->createMock(ProductRepository::class);
        $repo->expects($this->never())->method('find');

        $variantRepo = $this->createMock(ProductVariantRepository::class);
        $em      = $this->createMock(EntityManagerInterface::class);
        $service = new StockStoreService($repo, $variantRepo, $em);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La quantité doit être supérieure à zéro.');

        $service->checkAndDecreaseStock(1, $quantity);
    }

    public function provideInvalidQuantities(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    private function buildVariant(?float $stock, bool $displayed = true, bool $productDisplayed = true): ProductVariant
    {
        $product = new Product();
        $product->setName('Concombre');
        $product->setIsDisplayed($productDisplayed);
        $product->setHasVariants(true);

        $variant = new ProductVariant();
        $variant->setLabel('Gros');
        $variant->setPrice(180);
        $variant->setStock($stock);
        $variant->setIsDisplayed($displayed);
        $variant->setProduct($product);

        return $variant;
    }

    private function serviceWithVariant(ProductVariant $variant): StockStoreService
    {
        $variantRepo = $this->createMock(ProductVariantRepository::class);
        $variantRepo->method('find')->willReturn($variant);

        return new StockStoreService(
            $this->createMock(ProductRepository::class),
            $variantRepo,
            $this->createMock(EntityManagerInterface::class)
        );
    }

    public function testCheckAndDecreaseVariantStockDecreasesWhenSufficient(): void
    {
        $variant = $this->buildVariant(10);

        $result = $this->serviceWithVariant($variant)->checkAndDecreaseVariantStock(1, 3);

        $this->assertSame($variant, $result);
        $this->assertEquals(7, $variant->getStock());
    }

    public function testCheckAndDecreaseVariantStockDoesNotDecrementWhenUntracked(): void
    {
        $variant = $this->buildVariant(null);

        $result = $this->serviceWithVariant($variant)->checkAndDecreaseVariantStock(1, 5);

        $this->assertSame($variant, $result);
        $this->assertNull($variant->getStock());
    }

    public function testCheckAndDecreaseVariantStockHidesVariantWhenReachingZero(): void
    {
        $variant = $this->buildVariant(3);

        $this->serviceWithVariant($variant)->checkAndDecreaseVariantStock(1, 3);

        $this->assertEquals(0, $variant->getStock());
        $this->assertFalse($variant->isDisplayed());
    }

    public function testCheckAndDecreaseVariantStockThrowsWhenInsufficient(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Stock insuffisant/');

        $this->serviceWithVariant($this->buildVariant(2))->checkAndDecreaseVariantStock(1, 5);
    }

    public function testCheckAndDecreaseVariantStockThrowsWhenVariantHidden(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Déclinaison non disponible/');

        $service = $this->serviceWithVariant($this->buildVariant(10, displayed: false));
        $service->checkAndDecreaseVariantStock(1, 1);
    }

    public function testCheckAndDecreaseVariantStockThrowsWhenProductHidden(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/Produit non disponible/');

        $service = $this->serviceWithVariant($this->buildVariant(10, productDisplayed: false));
        $service->checkAndDecreaseVariantStock(1, 1);
    }
}
