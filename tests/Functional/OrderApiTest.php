<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\DataFixtures\AppFixtures;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Doctrine\ORM\Tools\SchemaTool;

class OrderApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        $databaseTool->loadFixtures([AppFixtures::class]);
    }

    public function testAccessSecuredEndpoint(): void
    {
        $this->client->request(
            'POST',
            '/api/login_check',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'admin@example.com',
                'password' => 'Adminpassword1@'
            ])
        );

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
        $token = $data['token'];

        $this->client->request(
            'GET',
            '/api/orders',
            [],
            [],
            ['HTTP_Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseIsSuccessful();
        $this->assertJson($this->client->getResponse()->getContent());
    }

    public function testGetOrdersWithoutTokenReturns401(): void
    {
        $this->client->request('GET', '/api/orders');
        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetOrderByIdForbiddenForOtherUser(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $order = $doctrine->getRepository(Order::class)->findOneBy([]);
        $this->assertNotNull($order, 'Aucune commande trouvée en fixtures');

        $admin = $doctrine->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/api/orders/' . $order->getId());

        $this->assertResponseStatusCodeSame(404);
    }

    public function testCreateOrderFailsWhenPickupDateIsPastCutoff(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $user = $doctrine->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        $product = $doctrine->getRepository(Product::class)->findOneBy([]);
        $this->assertNotNull($product);
        $product->setStock(10);
        $doctrine->getManager()->flush();

        $pickupDate = (new \DateTimeImmutable('-1 day'))->format('Y-m-d\TH:i:s');

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/orders/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'pickupDate' => $pickupDate,
                'items'      => [['productId' => $product->getId(), 'quantity' => 1]],
            ])
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function testEditOrderSuccess(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $order = $doctrine->getRepository(Order::class)->findOneBy([]);
        $this->assertNotNull($order, 'Aucune commande trouvée en fixtures');

        $owner = $order->getUser();

        $product = $doctrine->getRepository(Product::class)->findOneBy([]);
        $product->setHasStock(true);
        $product->setIsDisplayed(true);
        $product->setStock(20);
        $doctrine->getManager()->flush();

        $pickupDate = (new \DateTimeImmutable('+3 days'))->format('Y-m-d\TH:i:s');

        $this->client->loginUser($owner);
        $this->client->request(
            'PUT',
            '/api/orders/' . $order->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'pickupDate' => $pickupDate,
                'items'      => [['productId' => $product->getId(), 'quantity' => 2]],
            ])
        );

        $this->assertResponseIsSuccessful();
        $this->assertIsArray(json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testCreateOrderWithoutTokenReturns401(): void
    {
        $this->client->request(
            'POST',
            '/api/orders/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'pickupDate' => (new \DateTimeImmutable('+3 days'))->format('Y-m-d\TH:i:s'),
                'items'      => [['productId' => 1, 'quantity' => 1]],
            ])
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testEditOrderWithoutTokenReturns401(): void
    {
        $doctrine = static::getContainer()->get('doctrine');
        $order    = $doctrine->getRepository(Order::class)->findOneBy([]);
        $this->assertNotNull($order);

        $this->client->request('PUT', '/api/orders/' . $order->getId());
        $this->assertResponseStatusCodeSame(401);
    }

    public function testEditOrderForbiddenForOtherUser(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $order = $doctrine->getRepository(Order::class)->findOneBy([]);
        $this->assertNotNull($order);

        $admin = $doctrine->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        $pickupDate = (new \DateTimeImmutable('+3 days'))->format('Y-m-d\TH:i:s');

        $this->client->loginUser($admin);
        $this->client->request(
            'PUT',
            '/api/orders/' . $order->getId(),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'pickupDate' => $pickupDate,
                'items'      => [['productId' => 1, 'quantity' => 1]],
            ])
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCreateOrderFailsWhenStockIsInsufficient(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $user = $doctrine->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);
        $this->assertNotNull($user, 'Utilisateur de test non trouvé');

        $product = $doctrine->getRepository(Product::class)->findOneBy([]);
        $this->assertNotNull($product, 'Aucun produit trouvé');

        $product->setHasStock(true);
        $product->setIsDisplayed(true);
        $product->setStock(1);
        $doctrine->getManager()->flush();

        $pickupDate = (new \DateTimeImmutable('+2 days'))->format('Y-m-d\TH:i:s');

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/orders/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'pickupDate' => $pickupDate,
                'items'      => [['productId' => $product->getId(), 'quantity' => 5]],
            ])
        );

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame(
            sprintf(
                'Stock insuffisant pour le produit : %s. Quantité restante : %d.',
                $product->getName(),
                $product->getStock()
            ),
            $data['error']
        );
    }
}
