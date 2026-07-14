<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Product;
use App\Entity\ProductOrder;
use App\Entity\User;
use Doctrine\ORM\Tools\SchemaTool;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BasketApiTest extends WebTestCase
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

    public function testBasketIsListedFirstWithComposition(): void
    {
        $this->client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);

        // Baskets are sorted at the head of the grid.
        $this->assertTrue($data[0]['isBasket'], 'Le panier doit apparaître en tête de liste');

        $basket = $data[0];
        $this->assertSame('Panier de la semaine', $basket['name']);
        $this->assertNotEmpty($basket['basketItems'], 'Le panier doit exposer sa composition');

        foreach ($basket['basketItems'] as $item) {
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('quantity', $item);
        }

        // Regular products carry the flag too, false with an empty composition.
        $regular = array_values(array_filter($data, static fn ($p) => !$p['isBasket']));
        $this->assertNotEmpty($regular);
        $this->assertSame([], $regular[0]['basketItems']);
    }

    public function testBuyingBasketCreatesSingleOrderLineAndDecrementsStock(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $basket = $doctrine->getRepository(Product::class)->findOneBy(['isBasket' => true]);
        $this->assertNotNull($basket, 'Le panier de fixtures doit exister');
        $basketId    = $basket->getId();
        $stockBefore = $basket->getStock();
        $priceBefore = $basket->getPrice();

        $user = $doctrine->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);

        $pickupDate = (new \DateTimeImmutable('+3 days'))->format('Y-m-d\TH:i:s');

        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/orders/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'pickupDate' => $pickupDate,
                'items'      => [['productId' => $basketId, 'quantity' => 1]],
            ])
        );

        $this->assertResponseStatusCodeSame(201);

        $doctrine->getManager()->clear();

        // The basket's own stock is decremented (component stocks untouched).
        $reloaded = $doctrine->getRepository(Product::class)->find($basketId);
        $this->assertEquals($stockBefore - 1, $reloaded->getStock());

        // Buying a basket produces one normal ProductOrder line: qty 1, no
        // variant, price frozen at the basket price.
        $lines = $doctrine->getRepository(ProductOrder::class)->findBy(['product' => $basketId]);
        $this->assertCount(1, $lines);
        $this->assertEquals(1.0, $lines[0]->getQuantity());
        $this->assertNull($lines[0]->getProductVariant());
        $this->assertSame($priceBefore, $lines[0]->getUnitPrice());
    }
}
