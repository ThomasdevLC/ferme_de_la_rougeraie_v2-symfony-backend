<?php

namespace App\Tests\Unit\Service;

use App\Dto\Order\Create\CartItemDto;
use App\Dto\Order\Create\OrderCreateDto;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Mapper\OrderMapper;
use App\Repository\Store\OrderStoreRepository;
use App\Service\Store\OrderStoreService;
use App\Service\Store\StockStoreService;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use PHPUnit\Framework\TestCase;

class OrderStoreServiceTest extends TestCase
{
    private function buildService(
        OrderStoreRepository $repo,
        StockStoreService $stockService,
        OrderMapper $mapper
    ): OrderStoreService {
        return new OrderStoreService($repo, $stockService, $mapper);
    }

    private function buildDto(DateTimeImmutable $pickupDate): OrderCreateDto
    {
        return new OrderCreateDto(
            items: [new CartItemDto(productId: 1, quantity: 1)],
            pickupDate: $pickupDate
        );
    }

    public function testCreateOrderSucceedsWhenPickupDateIsWithinWindow(): void
    {
        $tz         = new DateTimeZone('Europe/Paris');
        $pickupDate = new DateTimeImmutable('+3 days', $tz);

        $product = new Product();
        $product->setHasStock(false);

        $order  = new Order();
        $user   = new User();

        $repo   = $this->createMock(OrderStoreRepository::class);
        $repo->expects($this->once())->method('save');

        $stock  = $this->createMock(StockStoreService::class);
        $stock->method('checkAndDecreaseStock')->willReturn($product);

        $mapper = $this->createMock(OrderMapper::class);
        $mapper->method('fromDto')->willReturn($order);

        $service = $this->buildService($repo, $stock, $mapper);
        $result  = $service->createOrderFromCart($this->buildDto($pickupDate), $user);

        $this->assertInstanceOf(Order::class, $result);
    }

    public function testCreateOrderFailsWhenPickupDateIsPastCutoff(): void
    {
        $this->expectException(DomainException::class);

        $tz         = new DateTimeZone('Europe/Paris');
        $pickupDate = new DateTimeImmutable('-1 day', $tz);

        $repo   = $this->createMock(OrderStoreRepository::class);
        $stock  = $this->createMock(StockStoreService::class);
        $mapper = $this->createMock(OrderMapper::class);

        $service = $this->buildService($repo, $stock, $mapper);
        $service->createOrderFromCart($this->buildDto($pickupDate), new User());
    }
}
