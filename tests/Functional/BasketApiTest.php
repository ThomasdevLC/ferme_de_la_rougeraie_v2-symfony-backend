<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Product;
use App\Entity\ProductOrder;
use App\Entity\User;
use App\Enum\ProductUnit;
use App\Validator\UniqueBasketName;
use Doctrine\ORM\Tools\SchemaTool;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    /**
     * Baskets persisted by the fixtures, ordered by name — the same order the
     * storefront grid uses.
     *
     * @return Product[]
     */
    private function persistedBaskets(): array
    {
        return static::getContainer()->get('doctrine')
            ->getRepository(Product::class)
            ->findBy(['isBasket' => true, 'isDeleted' => false], ['name' => 'ASC']);
    }

    public function testEveryDisplayedBasketIsListedFirstWithItsComposition(): void
    {
        $baskets = $this->persistedBaskets();
        $this->assertGreaterThan(1, count($baskets), 'Les fixtures doivent fournir plusieurs paniers');

        $this->client->request('GET', '/api/products');
        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($data);

        // Every basket sits at the head of the grid, before any regular product.
        $head = array_slice($data, 0, count($baskets));

        foreach ($head as $position => $payload) {
            $this->assertTrue($payload['isBasket'], "L'élément $position doit être un panier");
        }

        // Compare to the persisted names (the TitleCaseListener normalises them,
        // e.g. "Panier de la semaine (petit)" -> "Panier De La Semaine (Petit)").
        $this->assertSame(
            array_map(static fn (Product $basket): string => $basket->getName(), $baskets),
            array_column($head, 'name')
        );

        foreach ($head as $payload) {
            // A basket is auto-categorised "Panier" (BASKET) at creation.
            $this->assertSame('BASKET', $payload['category']['key']);
            $this->assertSame('Panier', $payload['category']['label']);

            $this->assertNotEmpty($payload['basketItems'], 'Un panier doit exposer sa composition');

            foreach ($payload['basketItems'] as $item) {
                $this->assertArrayHasKey('name', $item);
                $this->assertArrayHasKey('quantity', $item);
                $this->assertArrayHasKey('unit', $item);
            }
        }

        // Regular products carry the flag too, false with an empty composition.
        $regular = array_values(array_filter($data, static fn ($p) => !$p['isBasket']));
        $this->assertNotEmpty($regular);
        $this->assertSame([], $regular[0]['basketItems']);
    }

    public function testBuyingBasketCreatesSingleOrderLineAndDecrementsStock(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $basket = $this->persistedBaskets()[0];
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

    /**
     * The UniqueBasketName violations raised for $product, if any.
     *
     * Matched on the constraint rather than the text, so the messages stay free
     * to change without breaking these tests.
     *
     * @return \Symfony\Component\Validator\ConstraintViolationInterface[]
     */
    private function uniqueNameViolations(Product $product): array
    {
        /** @var ValidatorInterface $validator */
        $validator = static::getContainer()->get(ValidatorInterface::class);

        $matches = [];
        foreach ($validator->validate($product) as $violation) {
            if ($violation->getConstraint() instanceof UniqueBasketName) {
                $matches[] = $violation;
            }
        }

        return $matches;
    }

    /**
     * Assert the clash is reported once, on the "name" field so the admin form
     * shows it under the Nom input, with $expectedFragment in the text.
     */
    private function assertNameClash(Product $product, string $expectedFragment): void
    {
        $violations = $this->uniqueNameViolations($product);

        $this->assertCount(1, $violations, 'Le doublon de nom doit être signalé');
        $this->assertSame('name', $violations[0]->getPropertyPath(), 'L\'erreur doit pointer le champ Nom');
        $this->assertStringContainsString($expectedFragment, $violations[0]->getMessage());
    }

    private function makeBasket(string $name): Product
    {
        $basket = new Product();
        $basket->setName($name);
        $basket->setUnit(ProductUnit::PIECE);
        $basket->setPrice(3000);
        $basket->markAsBasket();

        return $basket;
    }

    public function testTwoBasketsCannotShareTheSameName(): void
    {
        $taken = $this->persistedBaskets()[0]->getName();

        $this->assertNameClash($this->makeBasket($taken), 'Un panier porte déjà ce nom');

        // The database collation is case- and accent-insensitive, and the name
        // is trimmed before storage: these are the same name too.
        $this->assertNameClash($this->makeBasket(mb_strtolower($taken)), 'Un panier porte déjà ce nom');
        $this->assertNameClash($this->makeBasket('  ' . $taken . '  '), 'Un panier porte déjà ce nom');
    }

    public function testTheSuggestedNameIsSpelledOutForTheAdmin(): void
    {
        $taken = $this->persistedBaskets()[0]->getName();

        $this->assertNameClash($this->makeBasket($taken), $taken . ' (petit)');
    }

    public function testAHiddenBasketClashSaysSo(): void
    {
        $doctrine = static::getContainer()->get('doctrine');

        $hidden = $this->persistedBaskets()[0];
        $hidden->setIsDisplayed(false);
        $doctrine->getManager()->flush();

        // Without this the admin searches the list for a basket that is not there.
        $this->assertNameClash($this->makeBasket($hidden->getName()), 'Un panier masqué porte déjà ce nom');
    }

    public function testADistinctBasketNameIsAccepted(): void
    {
        $this->assertSame([], $this->uniqueNameViolations($this->makeBasket('Panier de Noël')));
    }

    public function testEditingABasketDoesNotClashWithItself(): void
    {
        $basket = $this->persistedBaskets()[0];
        $basket->setStock(42);

        $this->assertSame([], $this->uniqueNameViolations($basket));
    }

    public function testRegularProductsMayStillShareAName(): void
    {
        $existing = static::getContainer()->get('doctrine')
            ->getRepository(Product::class)
            ->findOneBy(['isBasket' => false]);

        $twin = new Product();
        $twin->setName($existing->getName());
        $twin->setUnit(ProductUnit::PIECE);
        $twin->setPrice(500);
        $twin->setCategory($existing->getCategory());

        $this->assertSame([], $this->uniqueNameViolations($twin));
    }
}
