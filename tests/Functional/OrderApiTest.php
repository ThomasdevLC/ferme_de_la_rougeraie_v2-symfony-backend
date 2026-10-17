<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\DataFixtures\AppFixtures;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
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

    public function testCancelOrderSuccess(): void
    {
        [$orderId, $ownerId, $productId, $orderedQty] = $this->prepareCancellableOrder();

        $this->client->request('DELETE', '/api/orders/' . $orderId, [], [], $this->authHeaders($ownerId));

        $this->assertResponseStatusCodeSame(204);
        $this->assertSame('', $this->client->getResponse()->getContent());

        $em    = $this->em();
        $order = $em->find(Order::class, $orderId);
        $this->assertTrue($order->isCancelled());
        $this->assertTrue($order->isDone());
        $this->assertNotEmpty($order->getProductOrders(), 'Les lignes doivent être conservées');
        $this->assertEqualsWithDelta(10 + $orderedQty, $em->find(Product::class, $productId)->getStock(), 0.001);

        // The cancelled order disappears from the customer's view.
        $this->client->request('GET', '/api/orders/' . $orderId, [], [], $this->authHeaders($ownerId));
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/api/orders', [], [], $this->authHeaders($ownerId));
        $this->assertResponseIsSuccessful();
        $ids = array_column(json_decode($this->client->getResponse()->getContent(), true), 'id');
        $this->assertNotContains($orderId, $ids);
    }

    public function testCancelOrderTwiceRestoresStockOnce(): void
    {
        [$orderId, $ownerId, $productId, $orderedQty] = $this->prepareCancellableOrder();

        $this->client->request('DELETE', '/api/orders/' . $orderId, [], [], $this->authHeaders($ownerId));
        $this->assertResponseStatusCodeSame(204);

        $this->client->request('DELETE', '/api/orders/' . $orderId, [], [], $this->authHeaders($ownerId));
        $this->assertResponseStatusCodeSame(403);

        $this->assertEqualsWithDelta(10 + $orderedQty, $this->em()->find(Product::class, $productId)->getStock(), 0.001);
    }

    public function testCancelOrderForbiddenForOtherUser(): void
    {
        [$orderId] = $this->prepareCancellableOrder();

        $admin = $this->em()->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);

        $this->client->loginUser($admin);
        $this->client->request('DELETE', '/api/orders/' . $orderId);

        $this->assertResponseStatusCodeSame(403);
        $this->assertFalse($this->em()->find(Order::class, $orderId)->isCancelled());
    }

    public function testCancelOrderPastCutoffReturns409(): void
    {
        [$orderId, $ownerId, $productId] = $this->prepareCancellableOrder();

        $em = $this->em();
        $em->find(Order::class, $orderId)->setPickupDate(new \DateTimeImmutable('-1 day'));
        $em->flush();

        $this->client->loginUser($em->find(User::class, $ownerId));
        $this->client->request('DELETE', '/api/orders/' . $orderId);

        $this->assertResponseStatusCodeSame(409);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Cette commande ne peut plus être annulée.', $data['error']);

        $em = $this->em();
        $this->assertFalse($em->find(Order::class, $orderId)->isCancelled());
        $this->assertEqualsWithDelta(10, $em->find(Product::class, $productId)->getStock(), 0.001);
    }

    public function testCancelOrderWithoutTokenReturns401(): void
    {
        $order = $this->em()->getRepository(Order::class)->findOneBy([]);
        $this->assertNotNull($order);

        $this->client->request('DELETE', '/api/orders/' . $order->getId());
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * Picks a fixture order, moves its pickup to D+5 (before cutoff) and gives
     * its first line's product a tracked stock of 10.
     *
     * @return array{0: int, 1: int, 2: int, 3: float} [orderId, ownerId, productId, quantity ordered of that product]
     */
    private function prepareCancellableOrder(): array
    {
        $em = $this->em();

        $order = $em->getRepository(Order::class)->findOneBy([]);
        $this->assertNotNull($order, 'Aucune commande trouvée en fixtures');
        $order->setPickupDate(new \DateTimeImmutable('+5 days'));

        $product = $order->getProductOrders()->first()->getProduct();
        $product->setHasStock(true);
        $product->setIsDisplayed(true);
        $product->setStock(10);
        $em->flush();

        // The same product may appear on several fixture lines.
        $orderedQty = 0.0;
        foreach ($order->getProductOrders() as $line) {
            if ($line->getProduct() === $product) {
                $orderedQty += $line->getQuantity();
            }
        }

        return [$order->getId(), $order->getUser()->getId(), $product->getId(), $orderedQty];
    }

    /**
     * Real JWT header: unlike loginUser(), it survives the kernel reboot
     * between several requests of the same test.
     */
    private function authHeaders(int $userId): array
    {
        $user = $this->em()->find(User::class, $userId);
        $jwt  = static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $jwt];
    }

    /**
     * A clean entity manager: a refused request (403/409) rolls back its
     * transaction, which closes the shared one.
     */
    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->resetManager();
    }
}
