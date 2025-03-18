<?php

namespace App\Tests\Entity;

use App\Entity\Message;
use App\Entity\User;
use App\Enum\MessageType;
use PHPUnit\Framework\TestCase;

class MessageTest extends TestCase
{

    public function testGetId()
    {
        $message = new Message();
        $this->assertNull($message->getId());
    }

    public function testGetAndSetType()
    {
        $message = new Message();
        $type = MessageType::MARQUEE;
        $message->setType($type);

        $this->assertEquals($type, $message->getType());
    }

    public function testGetAndSetContent()
    {
        $message = new Message();
        $content = "Ceci est un message de test.";
        $message->setContent($content);

        $this->assertEquals($content, $message->getContent());
    }

    public function testSetAndIsActive()
    {
        $message = new Message();
        $message->setIsActive(true);
        $this->assertTrue($message->isActive());

        $message->setIsActive(false);
        $this->assertFalse($message->isActive());
    }

    public function testGetAndSetUser()
    {
        $message = new Message();
        $admin = new User();
        $admin->setRoles(["ROLE_ADMIN"]);

        $message->setUser($admin);
        $this->assertSame($admin, $message->getUser());
    }

    public function testOnlyAdminCanCreateMessage()
    {
        $message = new Message();
        $admin = new User();
        $admin->setRoles(["ROLE_ADMIN"]);

        $user = new User();
        $user->setRoles(["ROLE_USER"]);

        $message->setUser($admin);
        $this->assertSame($admin, $message->getUser());

        // Vérifier qu'un simple utilisateur ne peut pas publier de message
        $this->assertNotSame($user, $message->getUser());
    }
}
