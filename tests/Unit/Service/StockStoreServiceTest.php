<?php

namespace App\Tests\Unit\Service;

use App\Entity\Product;
use App\Repository\Admin\ProductRepository;
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

        $em      = $this->createMock(EntityManagerInterface::class);
        $service = new StockStoreService($repo, $em);

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

        $em      = $this->createMock(EntityManagerInterface::class);
        $service = new StockStoreService($repo, $em);

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

        $em      = $this->createMock(EntityManagerInterface::class);
        $service = new StockStoreService($repo, $em);

        $service->checkAndDecreaseStock(1, 5);
    }
}
