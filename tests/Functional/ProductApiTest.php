<?php

namespace App\Tests\Functional;

use App\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\DataFixtures\AppFixtures;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class ProductApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        $databaseTool->loadFixtures([AppFixtures::class]);
    }

    public function testGetProducts(): void
    {
        $this->client->request('GET', '/api/products');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response);

        $data = json_decode($response, true);

        // 🔥 Assertions utiles
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('price', $data[0]);
    }

    public function testProductWithoutStockManagementAppearsWithNullStock(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $product = $doctrine->getRepository(Product::class)->findOneBy([]);
        $this->assertNotNull($product);

        $product->setHasStock(false);
        $product->setIsDisplayed(true);
        $doctrine->getManager()->flush();

        $this->client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $found = array_filter($data, fn($p) => $p['id'] === $product->getId());
        $this->assertNotEmpty($found, 'Le produit doit apparaître dans la liste');

        $productData = array_values($found)[0];
        $this->assertFalse($productData['hasStock']);
        $this->assertNull($productData['stock']);
    }
}