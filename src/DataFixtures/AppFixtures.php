<?php

namespace App\DataFixtures;

use App\Entity\Message;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductOrder;
use App\Entity\User;
use App\Enum\MessageType;
use App\Enum\PickupDay;
use App\Enum\ProductUnit;
use DateTimeImmutable;
use DateTimeZone;
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

        $produceNames = [
            'Carotte',
            'Tomate',
            'Pomme',
            'Laitue',
            'Concombre',
            'Asperges',
            'Fraise',
            'Poivron',
            'Aubergine',
            'Courgette',
        ];

        // --- Création de l'admin ---
        $admin = new User();
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setEmail('admin@example.com');
        $admin->setPhone($faker->phoneNumber());
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Adminpassword1@'));
        $manager->persist($admin);

        // --- Création d'utilisateurs standards ---
        $users = [];
        for ($i = 0; $i < 5; $i++) {
            $user = new User();
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setEmail($faker->unique()->email());
            $user->setPhone($faker->phoneNumber());
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'Userpassword1@'));
            $manager->persist($user);
            $users[] = $user;
        }

        // --- Création de produits ---
        $products = [];
        for ($i = 0; $i < 10; $i++) {
            $product = new Product();

            $product->setName($faker->randomElement($produceNames));

            // Prix en centimes
            $euros = $faker->numberBetween(0, 9);
            $cents = $faker->randomElement([0, 25, 40, 50, 55, 65, 75, 85]);
            $product->setPrice($euros * 100 + $cents);

            // Unité
            $unit = $faker->randomElement([
                ProductUnit::PIECE,
                ProductUnit::BUNDLE,
                ProductUnit::BUNCH,
                ProductUnit::LITER,
                ProductUnit::KG,
            ]);
            $product->setUnit($unit);

            if ($unit === ProductUnit::KG) {
                $product->setInter($faker->randomElement([0.1, 0.2, 0.25, 0.5]));
            }

            $product->setIsDisplayed($faker->boolean());
            $product->setHasStock($faker->boolean());
            $product->setStock($faker->numberBetween(0, 100));
            $product->setLimited($faker->boolean());
            $product->setDiscount($faker->boolean());
            $product->setDiscountText($faker->boolean() ? $faker->sentence() : null);
            $product->setImage('default.jpg');
            $product->setUser($admin);

            $manager->persist($product);
            $products[] = $product;
        }

        // --- Création de commandes ---
        foreach ($users as $user) {
            for ($j = 0; $j < rand(1, 2); $j++) {
                $order = new Order();
                $order->setUser($user);
                $order->setTotal(0);
                $order->setCreatedAt(new \DateTimeImmutable());

                $day = $faker->randomElement(['tuesday', 'friday']);

                $pickupDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/Paris')))
                    ->modify("next {$day}")
                    ->setTime(0, 0, 0);

                $order->setPickupDate($pickupDate);

                $manager->persist($order);

                // Ajout de produits à la commande
                $orderTotal = 0;
                for ($k = 0; $k < rand(1, 4); $k++) {
                    $product      = $faker->randomElement($products);
                    $quantity     = rand(1, 5);
                    $unitPrice    = $product->getPrice();

                    $productOrder = new ProductOrder();
                    $productOrder->setOrder($order);
                    $productOrder->setProduct($product);
                    $productOrder->setQuantity($quantity);
                    $productOrder->setUnitPrice($unitPrice);

                    $manager->persist($productOrder);

                    $orderTotal += $quantity * $unitPrice;
                }

                $order->setTotal($orderTotal);
                $manager->persist($order);
            }
        }

        // --- Création de messages ---
        $messageTypes = [
            MessageType::MARQUEE,
            MessageType::CLOSEDSHOP,
        ];

        foreach ($messageTypes as $type) {
            $activeMessage = new Message();
            $activeMessage->setUser($admin);
            $activeMessage->setType($type);
            $activeMessage->setContent($faker->sentence());
            $activeMessage->setIsActive(true);
            $manager->persist($activeMessage);

            $inactiveMessage = new Message();
            $inactiveMessage->setUser($admin);
            $inactiveMessage->setType($type);
            $inactiveMessage->setContent($faker->sentence());
            $inactiveMessage->setIsActive(false);
            $manager->persist($inactiveMessage);
        }

        $manager->flush();
    }
}
