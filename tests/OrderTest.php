<?php

namespace App\Tests;

use App\Entity\Order;
use App\Entity\ProductOrder;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Enum\PickupDay;
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

    public function testGetAndSetPickup()
    {
        $order = new Order();
        $pickup = PickupDay::TUESDAY;
        $order->setPickup($pickup);

        $this->assertEquals($pickup, $order->getPickup());
    }

    public function testGetAndSetStatus()
    {
        $order = new Order();
        $status = OrderStatus::PENDING;
        $order->setStatus($status);

        $this->assertEquals($status, $order->getStatus());
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

        $productOrder->setOrderId($order);
        $order->addProductOrder($productOrder);

        $this->assertCount(1, $order->getProductOrders());
        $this->assertTrue($order->getProductOrders()->contains($productOrder));
        $this->assertSame($order, $productOrder->getOrderId());

        // Suppression du ProductOrder
        $order->removeProductOrder($productOrder);

        $this->assertCount(0, $order->getProductOrders());
        $this->assertNull($productOrder->getOrderId());
    }
}
