<?php

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductOrder;
use App\Entity\User;
use App\Enum\ProductUnit;
use App\Repository\Admin\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProductRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private User $admin;

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
    }

    public function testFindDeletableProductIds(): void
    {
        $unusedProduct = $this->createProduct('Unused');
        $doneOrderProduct = $this->createProduct('Done order only');
        $pendingOrderProduct = $this->createProduct('Pending and done orders');

        $this->addOrderLine($doneOrderProduct, true);
        $this->addOrderLine($pendingOrderProduct, true);
        $this->addOrderLine($pendingOrderProduct, false);
        $this->em->flush();

        /** @var ProductRepository $repository */
        $repository = $this->em->getRepository(Product::class);
        $deletableIds = $repository->findDeletableProductIds([
            $unusedProduct->getId(),
            $doneOrderProduct->getId(),
            $pendingOrderProduct->getId(),
        ]);

        $this->assertContains($unusedProduct->getId(), $deletableIds);
        $this->assertContains($doneOrderProduct->getId(), $deletableIds);
        $this->assertNotContains($pendingOrderProduct->getId(), $deletableIds);
    }

    private function createProduct(string $name): Product
    {
        $product = (new Product())
            ->setName($name)
            ->setPrice(100)
            ->setUnit(ProductUnit::PIECE)
            ->setImage('default.jpg')
            ->setUser($this->admin);

        $this->em->persist($product);

        return $product;
    }

    private function addOrderLine(Product $product, bool $done): void
    {
        $order = (new Order())
            ->setUser($this->admin)
            ->setTotal(100)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setPickupDate(new \DateTimeImmutable('+3 days'))
            ->setDone($done);

        $line = (new ProductOrder())
            ->setProduct($product)
            ->setOrder($order)
            ->setQuantity(1)
            ->setUnitPrice(100);

        $this->em->persist($order);
        $this->em->persist($line);
    }
}
