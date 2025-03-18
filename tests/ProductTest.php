<?php

namespace App\Tests\Entity;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\ProductOrder;
use App\Enum\ProductUnit;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testGetId()
    {
        $product = new Product();
        $this->assertNull($product->getId());
    }

    public function testGetAndSetName()
    {
        $product = new Product();
        $name = "Apple";
        $product->setName($name);

        $this->assertEquals($name, $product->getName());
    }

    public function testGetAndSetPrice()
    {
        $product = new Product();
        $price = 150;
        $product->setPrice($price);

        $this->assertEquals($price, $product->getPrice());
    }

    public function testGetAndSetUnit()
    {
        $product = new Product();
        $unit = ProductUnit::KG;
        $product->setUnit($unit);

        $this->assertEquals($unit, $product->getUnit());
    }

    public function testGetAndSetInter()
    {
        $product = new Product();
        $inter = 12.5;
        $product->setInter($inter);

        $this->assertEquals($inter, $product->getInter());
    }

    public function testSetAndIsDisplayed()
    {
        $product = new Product();
        $product->setIsDisplayed(true);
        $this->assertTrue($product->isDisplayed());

        $product->setIsDisplayed(false);
        $this->assertFalse($product->isDisplayed());
    }

    public function testSetAndHasStock()
    {
        $product = new Product();
        $product->setHasStock(true);
        $this->assertTrue($product->hasStock());

        $product->setHasStock(false);
        $this->assertFalse($product->hasStock());
    }

    public function testGetAndSetStock()
    {
        $product = new Product();
        $stock = 50;
        $product->setStock($stock);

        $this->assertEquals($stock, $product->getStock());
    }

    public function testSetAndIsLimited()
    {
        $product = new Product();
        $product->setLimited(true);
        $this->assertTrue($product->isLimited());

        $product->setLimited(false);
        $this->assertFalse($product->isLimited());
    }

    public function testSetAndIsDiscount()
    {
        $product = new Product();
        $product->setDiscount(true);
        $this->assertTrue($product->isDiscount());

        $product->setDiscount(false);
        $this->assertFalse($product->isDiscount());
    }

    public function testGetAndSetDiscountText()
    {
        $product = new Product();
        $discountText = "- 10% ";
        $product->setDiscountText($discountText);

        $this->assertEquals($discountText, $product->getDiscountText());
    }

    public function testGetAndSetImage()
    {
        $product = new Product();
        $image = "https://example.com/image.jpg";
        $product->setImage($image);

        $this->assertEquals($image, $product->getImage());
    }

    public function testGetAndSetUser()
    {
        $product = new Product();
        $user = new User();
        $user->setRoles(["ROLE_ADMIN"]); // Seul un admin peut créer un produit
        $product->setUser($user);

        $this->assertSame($user, $product->getUser());
    }

    public function testAddAndRemoveProductOrder()
    {
        $product = new Product();
        $productOrder = new ProductOrder();

        // Vérifier l'ajout
        $this->assertCount(0, $product->getProductOrders());
        $product->addProductOrder($productOrder);
        $this->assertCount(1, $product->getProductOrders());
        $this->assertTrue($product->getProductOrders()->contains($productOrder));

        // Vérifier la suppression
        $product->removeProductOrder($productOrder);
        $this->assertCount(0, $product->getProductOrders());
    }
}
