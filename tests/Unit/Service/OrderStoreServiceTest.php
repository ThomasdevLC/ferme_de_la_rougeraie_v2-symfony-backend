<?php

namespace App\Tests\Unit\Service;

use App\Dto\Order\Create\CartItemDto;
use App\Dto\Order\Create\OrderCreateDto;
use App\Dto\Order\Display\OrderDetailsDto;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductOrder;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Mapper\OrderMapper;
use App\Repository\Store\OrderStoreRepository;
use App\Service\Store\OrderStoreService;
use App\Service\Store\StockStoreService;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OrderStoreServiceTest extends TestCase
{
    private function buildService(
        OrderStoreRepository $repo,
        StockStoreService $stockService,
        OrderMapper $mapper
    ): OrderStoreService {
        // Run the transactional callback directly: no real database here.
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $func) => $func());

        return new OrderStoreService($repo, $stockService, $mapper, $em);
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

    public function testEditExpiredOrderFails(): void
    {
        $tz    = new DateTimeZone('Europe/Paris');
        $order = (new Order())->setPickupDate(new DateTimeImmutable('-1 day', $tz));
        $user  = new User();

        $repo = $this->createMock(OrderStoreRepository::class);
        $repo->method('findOneByIdAndUserForUpdate')->willReturn($order);

        $stock = $this->createMock(StockStoreService::class);
        $stock->expects($this->never())->method('increaseStock');
        $stock->expects($this->never())->method('checkAndDecreaseStock');

        $mapper = $this->createMock(OrderMapper::class);

        $service = $this->buildService($repo, $stock, $mapper);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cette commande ne peut plus être modifiée.');

        $service->editOrder(
            orderId: 1,
            dto: $this->buildDto(new DateTimeImmutable('+3 days', $tz)),
            user: $user
        );
    }

    public function testEditOrderRestoresVariantStockForVariantLines(): void
    {
        $tz    = new DateTimeZone('Europe/Paris');
        $order = (new Order())->setPickupDate(new DateTimeImmutable('+3 days', $tz));

        $variant = new ProductVariant();
        $this->setId($variant, 5);

        $order->addProductOrder(
            (new ProductOrder())
                ->setProduct(new Product())
                ->setProductVariant($variant)
                ->setQuantity(2)
                ->setUnitPrice(100)
        );

        $repo = $this->createMock(OrderStoreRepository::class);
        $repo->method('findOneByIdAndUserForUpdate')->willReturn($order);

        $stock = $this->createMock(StockStoreService::class);
        // Variant line must restore variant stock, not product stock.
        $stock->expects($this->once())->method('increaseVariantStock')->with(5, 2.0);
        $stock->expects($this->never())->method('increaseStock');
        $stock->method('checkAndDecreaseStock')->willReturn(new Product());

        $mapper = $this->createMock(OrderMapper::class);
        $mapper->method('toDto')->willReturn($this->buildOrderDetailsDto());

        $service = $this->buildService($repo, $stock, $mapper);
        $service->editOrder(
            orderId: 1,
            dto: $this->buildDto(new DateTimeImmutable('+3 days', $tz)),
            user: new User()
        );
    }

    public function testCancelOrderRestoresStockAndMarksOrderCancelledAndDone(): void
    {
        $tz    = new DateTimeZone('Europe/Paris');
        $order = (new Order())->setPickupDate(new DateTimeImmutable('+3 days', $tz));

        $product = new Product();
        $this->setId($product, 3);
        $variant = new ProductVariant();
        $this->setId($variant, 5);

        $order->addProductOrder(
            (new ProductOrder())->setProduct($product)->setQuantity(1.5)->setUnitPrice(100)
        );
        $order->addProductOrder(
            (new ProductOrder())->setProduct(new Product())->setProductVariant($variant)->setQuantity(2)->setUnitPrice(100)
        );

        $repo = $this->createMock(OrderStoreRepository::class);
        $repo->method('findOneByIdAndUserForUpdate')->willReturn($order);
        $repo->expects($this->once())->method('save')->with($order);

        $stock = $this->createMock(StockStoreService::class);
        $stock->expects($this->once())->method('increaseStock')->with(3, 1.5);
        $stock->expects($this->once())->method('increaseVariantStock')->with(5, 2.0);

        $service = $this->buildService($repo, $stock, $this->createMock(OrderMapper::class));
        $service->cancelOrder(orderId: 1, user: new User());

        $this->assertTrue($order->isCancelled());
        $this->assertTrue($order->isDone());
        // Lines are kept for the admin history.
        $this->assertCount(2, $order->getProductOrders());
    }

    public function testCancelExpiredOrderFails(): void
    {
        $tz    = new DateTimeZone('Europe/Paris');
        $order = (new Order())->setPickupDate(new DateTimeImmutable('-1 day', $tz));
        $order->addProductOrder(
            (new ProductOrder())->setProduct(new Product())->setQuantity(1)->setUnitPrice(100)
        );

        $repo = $this->createMock(OrderStoreRepository::class);
        $repo->method('findOneByIdAndUserForUpdate')->willReturn($order);
        $repo->expects($this->never())->method('save');

        $stock = $this->createMock(StockStoreService::class);
        $stock->expects($this->never())->method('increaseStock');
        $stock->expects($this->never())->method('increaseVariantStock');

        $service = $this->buildService($repo, $stock, $this->createMock(OrderMapper::class));

        try {
            $service->cancelOrder(orderId: 1, user: new User());
            $this->fail('DomainException expected');
        } catch (DomainException $e) {
            $this->assertSame('Cette commande ne peut plus être annulée.', $e->getMessage());
        }

        $this->assertFalse($order->isCancelled());
    }

    public function testCancelUnknownOrderFails(): void
    {
        $repo = $this->createMock(OrderStoreRepository::class);
        $repo->method('findOneByIdAndUserForUpdate')->willReturn(null);
        $repo->expects($this->never())->method('save');

        $stock = $this->createMock(StockStoreService::class);
        $stock->expects($this->never())->method('increaseStock');
        $stock->expects($this->never())->method('increaseVariantStock');

        $service = $this->buildService($repo, $stock, $this->createMock(OrderMapper::class));

        $this->expectException(AccessDeniedException::class);

        $service->cancelOrder(orderId: 999, user: new User());
    }

    private function buildOrderDetailsDto(): OrderDetailsDto
    {
        return new OrderDetailsDto(
            id: 1,
            total: 0.0,
            pickupDate: new DateTimeImmutable(),
            pickupDay: 1,
            createdAt: new DateTimeImmutable(),
            done: false,
            isEditable: true,
            items: []
        );
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
