<?php

namespace App\Tests\Functional;

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

        // 🔥 1. Création du schéma (OBLIGATOIRE ici)
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();

        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        // 🔥 2. Chargement des fixtures
        $databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();

        $databaseTool->loadFixtures([AppFixtures::class]);
    }

    public function testAccessSecuredEndpoint(): void
    {
        // 🔐 Login
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

        // 🔑 Appel sécurisé
        $this->client->request(
            'GET',
            '/api/orders',
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer ' . $token
            ]
        );

        // DEBUG si besoin
        // dump($this->client->getResponse()->getStatusCode());
        // dump($this->client->getResponse()->getContent());

        $this->assertResponseIsSuccessful();

        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response);
    }
}