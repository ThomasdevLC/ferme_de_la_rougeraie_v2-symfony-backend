<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductCategory;
use App\Enum\ProductUnit;
use App\Repository\Store\ProductStoreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductStoreRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private User $admin;
    private ProductStoreRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $databaseTool = static::getContainer()
            ->get(DatabaseToolCollection::class)
            ->get();
        $databaseTool->loadFixtures([AppFixtures::class]);

        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->admin = $this->em->getRepository(User::class)
            ->findOneBy(['email' => 'admin@example.com']);
        $this->repository = static::getContainer()->get(ProductStoreRepository::class);
    }

    public function testVariantProductIsListedOnlyWithAtLeastOneDisplayedVariant(): void
    {
        $withDisplayed = $this->createVariantProduct('Variant visible', [true, false]);
        $allHidden     = $this->createVariantProduct('Variant tout caché', [false, false]);
        $this->em->flush();

        $ids = array_map(
            fn(Product $p) => $p->getId(),
            $this->repository->findDisplayedAvailableProducts()
        );

        $this->assertContains($withDisplayed->getId(), $ids);
        $this->assertNotContains($allHidden->getId(), $ids);
    }

    public function testOnlyDisplayedVariantsAreLoaded(): void
    {
        $product = $this->createVariantProduct('Variant mixte', [true, true, false]);
        $this->em->flush();
        $productId = $product->getId();
        $this->em->clear();

        $loaded = null;
        foreach ($this->repository->findDisplayedAvailableProducts() as $p) {
            if ($p->getId() === $productId) {
                $loaded = $p;
                break;
            }
        }

        $this->assertNotNull($loaded);
        $this->assertCount(2, $loaded->getVariants());
    }

    /**
     * @param bool[] $variantDisplayedFlags one flag per variant to create
     */
    private function createVariantProduct(string $name, array $variantDisplayedFlags): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setUnit(ProductUnit::PIECE)
            ->setCategory(ProductCategory::VEGETABLE)
            ->setImage('default.jpg')
            ->setUser($this->admin)
            ->setIsDisplayed(true)
            ->setHasVariants(true);

        $this->em->persist($product);

        foreach ($variantDisplayedFlags as $i => $displayed) {
            $variant = (new ProductVariant())
                ->setLabel('V' . $i)
                ->setPrice(100 + $i)
                ->setStock(5)
                ->setIsDisplayed($displayed)
                ->setPosition($i);
            $variant->setProduct($product);
            $product->addVariant($variant);
            $this->em->persist($variant);
        }

        return $product;
    }
}
