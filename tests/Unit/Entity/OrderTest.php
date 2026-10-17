<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Order;
use App\Entity\ProductOrder;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testGetId()
    {
        $order = new Order();
        $this->assertNull($order->getId());
    }

    public function testGetAndSetTotal()
    {
        $order = new Order();
        $total = 150;
        $order->setTotal($total);

        $this->assertEquals($total, $order->getTotal());
    }

    public function testGetAndSetCreatedAt()
    {
        $order = new Order();
        $createdAt = new \DateTimeImmutable();
        $order->setCreatedAt($createdAt);

        $this->assertEquals($createdAt, $order->getCreatedAt());
    }

    public function testGetAndSetUser()
    {
        $order = new Order();
        $user = new User();
        $order->setUser($user);

        $this->assertSame($user, $order->getUser());
    }

    public function testAddAndRemoveProductOrder()
    {
        $order = new Order();
        $productOrder = new ProductOrder();

        $productOrder->setOrder($order);
        $order->addProductOrder($productOrder);

        $this->assertCount(1, $order->getProductOrders());
        $this->assertTrue($order->getProductOrders()->contains($productOrder));
        $this->assertSame($order, $productOrder->getOrder());

        // Suppression du ProductOrder
        $order->removeProductOrder($productOrder);

        $this->assertCount(0, $order->getProductOrders());
        $this->assertNull($productOrder->getOrder());
    }

    public function testPickupDateLabelShowsExactDayAndDate()
    {
        $order = (new Order())->setPickupDate(
            new \DateTimeImmutable('2026-10-20 00:00:00', new \DateTimeZone('Europe/Paris'))
        );

        $this->assertSame('Mardi 20 oct.', $order->getPickupDateLabel());
    }

    public function testPickupDateLabelDoesNotShiftDayForDateLoadedInUtc()
    {
        // Doctrine hydrates the property directly (no setter): the stored Paris
        // midnight comes back labelled with the server timezone (UTC).
        $order = new Order();
        $ref = new \ReflectionProperty($order, 'pickupDate');
        $ref->setValue($order, new \DateTimeImmutable('2026-10-16 00:00:00', new \DateTimeZone('UTC')));

        $this->assertStringStartsWith('Vendredi 16', $order->getPickupDateLabel());
    }

    public function testNewOrderIsNotCancelled()
    {
        $order = new Order();

        $this->assertFalse($order->isCancelled());
        $this->assertNull($order->getCancelledAt());
    }

    public function testCancelSetsCancelledAt()
    {
        $order = new Order();
        $order->cancel();

        $this->assertTrue($order->isCancelled());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getCancelledAt());
    }
}
