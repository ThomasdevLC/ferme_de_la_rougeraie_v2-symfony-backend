<?php

namespace App\Tests;

use App\Entity\ProductOrder;
use App\Entity\Product;
use App\Entity\Order;
use PHPUnit\Framework\TestCase;

class ProductOrderTest extends TestCase
{

    public function testGetId()
    {
        $productOrder = new ProductOrder();
        $this->assertNull($productOrder->getId());
    }

    public function testGetAndSetQuantity()
    {
        $productOrder = new ProductOrder();
        $quantity = 5;
        $productOrder->setQuantity($quantity);

        $this->assertEquals($quantity, $productOrder->getQuantity());
    }

    public function testGetAndSetUnitPrice()
    {
        $productOrder = new ProductOrder();
        $unitPrice = 150;
        $productOrder->setUnitPrice($unitPrice);

        $this->assertEquals($unitPrice, $productOrder->getUnitPrice());
    }

    public function testGetAndSetProduct()
    {
        $productOrder = new ProductOrder();
        $product = new Product();
        $productOrder->setProduct($product);

        $this->assertSame($product, $productOrder->getProduct());
    }

    public function testGetAndSetOrder()
    {
        $productOrder = new ProductOrder();
        $order = new Order();
        $productOrder->setOrderId($order);

        $this->assertSame($order, $productOrder->getOrderId());
    }
}
