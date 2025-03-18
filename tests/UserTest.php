<?php

namespace App\Tests;

use App\Entity\Product;
use App\Entity\Order;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase



{
    public function testGetId()
    {
        $user = new User();
        $this->assertNull($user->getId());
    }

    public function testGetAndSetEmail()
    {
        $user = new User();
        $email = "test@example.com";
        $user->setEmail($email);

        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($email, $user->getUserIdentifier());
    }

    public function testGetAndSetFirstName()
    {
        $user = new User();
        $firstName = "Thomas";
        $user->setFirstName($firstName);

        $this->assertEquals($firstName, $user->getFirstName());
    }

    public function testGetAndSetLastName()
    {
        $user = new User();
        $lastName = "Dupont";
        $user->setLastName($lastName);

        $this->assertEquals($lastName, $user->getLastName());
    }

    public function testGetAndSetPhone()
    {
        $user = new User();
        $phone = "0123456789";
        $user->setPhone($phone);

        $this->assertEquals($phone, $user->getPhone());
    }

    public function testGetAndSetPassword()
    {
        $user = new User();
        $password = "securepassword";
        $user->setPassword($password);

        $this->assertEquals($password, $user->getPassword());
    }

    public function testRoles()
    {
        $user = new User();
        $this->assertContains("ROLE_USER", $user->getRoles());

        $user->setRoles(["ROLE_ADMIN"]);
        $this->assertContains("ROLE_ADMIN", $user->getRoles());
        $this->assertContains("ROLE_USER", $user->getRoles());
    }

    public function testEraseCredentials()
    {
        $user = new User();
        $user->eraseCredentials();
        $this->assertTrue(true);
    }

    public function testAddAndRemoveProduct()
    {
        $user = new User();
        $product = new Product();

        // Vérifier l'ajout du produit
        $this->assertCount(0, $user->getProducts());
        $user->addProduct($product);
        $this->assertCount(1, $user->getProducts());
        $this->assertTrue($user->getProducts()->contains($product));
        $this->assertSame($user, $product->getUser());

        // Vérifier la suppression du produit
        $user->removeProduct($product);
        $this->assertCount(0, $user->getProducts());
        $this->assertNull($product->getUser());
    }

    public function testAddAndRemoveOrder()
    {
        $user = new User();
        $order = new Order();

        // Vérifier l'ajout de la commande
        $this->assertCount(0, $user->getOrders());
        $user->addOrder($order);
        $this->assertCount(1, $user->getOrders());
        $this->assertTrue($user->getOrders()->contains($order));
        $this->assertSame($user, $order->getUser());

        // Vérifier la suppression de la commande
        $user->removeOrder($order);
        $this->assertCount(0, $user->getOrders());
        $this->assertNull($order->getUser());
    }
}
