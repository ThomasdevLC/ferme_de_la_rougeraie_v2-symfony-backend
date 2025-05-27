<?php
namespace App\Tests\Controller;

use App\Entity\User;
use App\Entity\Product;
use App\DataFixtures\AppFixtures;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrderControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private $databaseTool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        $this->databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();
    }

    public function testCreateOrderFailsWhenStockIsInsufficient(): void
    {
        // 1) Charge les fixtures
        $this->databaseTool->loadFixtures([AppFixtures::class]);

        // 2) Récupère un utilisateur et un produit
        $doctrine = static::getContainer()->get('doctrine');
        $user      = $doctrine->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);
        $this->assertNotNull($user, 'Utilisateur de test non trouvé');

        $product = $doctrine->getRepository(Product::class)->findOneBy([]);
        $this->assertNotNull($product, 'Aucun produit trouvé');
        // On override le stock à 1 pour être sûr
        $product->setStock(1);
        $doctrine->getManager()->flush();

        // 3) Simule la connexion et envoie la requête
        $this->client->loginUser($user);
        $this->client->request(
            'POST',
            '/api/orders/create',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'pickup' => 'TUESDAY',
                'items'  => [
                    ['productId' => $product->getId(), 'quantity' => 5],
                ],
            ])
        );

        // 4) Assertions sur le code HTTP
        $this->assertResponseStatusCodeSame(400);

        // 5) Décodage du JSON et assertions PHPUnit
        $responseContent = $this->client->getResponse()->getContent();
        $this->assertJson($responseContent, 'La réponse doit être du JSON valide');

        $data = json_decode($responseContent, true);
        $this->assertArrayHasKey('error', $data, 'Il doit y avoir une clé "error" dans la réponse JSON');

        $expected = sprintf(
            'Stock insuffisant pour le produit : %s. Quantité restante : %d.',
            $product->getName(),
            $product->getStock()
        );
        $this->assertSame($expected, $data['error'], 'Le message d’erreur doit correspondre exactement');
    }
}
