<?php

namespace App\Tests\Unit\Entity;

use App\Entity\BasketItem;
use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class BasketItemTest extends TestCase
{
    public function testGetId(): void
    {
        $basketItem = new BasketItem();
        $this->assertNull($basketItem->getId());
    }

    public function testGetAndSetBasket(): void
    {
        $basketItem = new BasketItem();
        $basket = new Product();
        $basketItem->setBasket($basket);

        $this->assertSame($basket, $basketItem->getBasket());
    }

    public function testGetAndSetProduct(): void
    {
        $basketItem = new BasketItem();
        $product = new Product();
        $basketItem->setProduct($product);

        $this->assertSame($product, $basketItem->getProduct());
    }

    public function testGetAndSetQuantity(): void
    {
        $basketItem = new BasketItem();
        $basketItem->setQuantity(3.5);

        $this->assertEquals(3.5, $basketItem->getQuantity());
    }

    public function testPositionDefaultsToZero(): void
    {
        $basketItem = new BasketItem();
        $this->assertSame(0, $basketItem->getPosition());
    }

    public function testGetAndSetPosition(): void
    {
        $basketItem = new BasketItem();
        $basketItem->setPosition(4);

        $this->assertSame(4, $basketItem->getPosition());
    }

    public function testToStringReturnsComponentName(): void
    {
        $product = (new Product())->setName('Tomates');
        $basketItem = (new BasketItem())->setProduct($product);

        $this->assertSame('Tomates', (string) $basketItem);
    }

    public function testToStringFallsBackWhenNoProduct(): void
    {
        $basketItem = new BasketItem();
        $this->assertSame('Composant', (string) $basketItem);
    }
}
