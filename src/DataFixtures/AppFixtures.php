<?php

namespace App\DataFixtures;

use App\Entity\Message;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductOrder;
use App\Entity\User;
use App\Enum\MessageType;
use App\Enum\OrderStatus;
use App\Enum\PickupDay;
use App\Enum\ProductUnit;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Création d'un administrateur
        $admin = new User();
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setEmail('admin@example.com');
        $admin->setPhone($faker->phoneNumber());
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'adminpassword'));
        $manager->persist($admin);

        // Création de plusieurs utilisateurs standards
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $user = new User();
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setEmail($faker->unique()->email());
            $user->setPhone($faker->phoneNumber());
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'userpassword'));
            $manager->persist($user);
            $users[] = $user;
        }

        // Création de produits (uniquement par l'admin)
        $products = [];
        for ($i = 0; $i < 10; $i++) {
            $product = new Product();
            $product->setName($faker->word());
            $product->setPrice($faker->numberBetween(10, 500));
            $product->setUnit($faker->randomElement([
                ProductUnit::PIECE,
                ProductUnit::BUNDLE,
                ProductUnit::BUNCH,
                ProductUnit::LITER,
                ProductUnit::KG
            ]));
            $product->setInter($faker->randomFloat(2, 1, 100));
            $product->setIsDisplayed($faker->boolean());
            $product->setHasStock($faker->boolean());
            $product->setStock($faker->numberBetween(0, 100));
            $product->setLimited($faker->boolean());
            $product->setDiscount($faker->boolean());
            $product->setDiscountText($faker->boolean() ? $faker->sentence() : null);
            $product->setImage($faker->imageUrl(640, 480, 'food'));
            $product->setUser($admin); // Seul l'admin peut créer des produits

            $manager->persist($product);
            $products[] = $product;
        }

        // Création de commandes pour les utilisateurs standards
        foreach ($users as $user) {
            for ($j = 0; $j < rand(1, 3); $j++) { // Chaque utilisateur passe 1 à 3 commandes
                $order = new Order();
                $order->setUser($user);
                $order->setTotal(0); // Le total sera calculé plus tard
                $order->setCreatedAt(new \DateTimeImmutable());
                $order->setPickup($faker->randomElement([PickupDay::TUESDAY, PickupDay::THURSDAY]));
                $order->setStatus($faker->randomElement([OrderStatus::PENDING, OrderStatus::DONE]));
                $manager->persist($order);

                // Ajout de produits à la commande
                $orderTotal = 0;
                for ($k = 0; $k < rand(1, 4); $k++) { // Chaque commande contient 1 à 4 produits
                    $product = $faker->randomElement($products);
                    $quantity = rand(1, 5);
                    $unitPrice = $product->getPrice();

                    $productOrder = new ProductOrder();
                    $productOrder->setOrderId($order);
                    $productOrder->setProduct($product);
                    $productOrder->setQuantity($quantity);
                    $productOrder->setUnitPrice($unitPrice);
                    $manager->persist($productOrder);

                    // Calcul du total de la commande
                    $orderTotal += $quantity * $unitPrice;
                }

                $order->setTotal($orderTotal);
                $manager->persist($order);
            }
        }

        for ($m = 0; $m < rand(1, 5); $m++) { // L'admin publie entre 1 et 5 messages visibles par tous
            $message = new Message();
            $message->setUser($admin);
            $message->setType($faker->randomElement([
                MessageType::MARQUEE,
                MessageType::CLOSEDSHOP,
            ]));
            $message->setContent($faker->sentence());
            $message->setIsActive($faker->boolean());

            $manager->persist($message);
        }

        $manager->flush();
    }
}
