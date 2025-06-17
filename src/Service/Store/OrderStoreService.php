<?php

namespace App\Service\Store;

use App\Dto\Order\Create\OrderCreateDto;
use App\Dto\Order\Display\OrderDetailsDto;
use App\Entity\Order;
use App\Entity\ProductOrder;
use App\Entity\User;
use App\Mapper\OrderMapper;
use App\Repository\Store\OrderStoreRepository;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OrderStoreService
{
    public function __construct(
        private OrderStoreRepository $orderStoreRepository,
        private StockStoreService    $stockService,
    ) {}

    /**
     * @param User $user
     * @return Order[]
     */
    public function getOrdersForUser(User $user): array
    {
        $orders = $this->orderStoreRepository->findOrdersByUser($user);
        return array_map(
            fn(Order $order) => OrderMapper::toDto($order),
            $orders
        );
    }

    /**
     * @throws AccessDeniedException if order not found or not owned by user
     */
    public function getOneOrderForUser(int $orderId, User $user): OrderDetailsDto
    {
        $order = $this->orderStoreRepository->findOneByIdAndUser($orderId, $user);
        if (!$order) {
            throw new AccessDeniedException('Order not found or unauthorized.');
        }

        return OrderMapper::toDto($order);
    }



    /**
     * @throws DomainException if cutoff is passed
     */
    public function createOrderFromCart(OrderCreateDto $dto, User $user): Order
    {
        // 1) Récupération directe de la date envoyée par le front
        $pickupDate = $dto->pickupDate
            ->setTimezone(new DateTimeZone('Europe/Paris'));

        // 2) Vérification du cutoff (veille 21h)
        $this->assertWithinOrderWindow($pickupDate);

        // 3) Préparation des lignes produits
        $productData = [];
        foreach ($dto->items as $item) {
            $product = $this->stockService
                ->checkAndDecreaseStock($item->productId, $item->quantity);

            $productData[] = [
                'product'  => $product,
                'quantity' => $item->quantity,
            ];
        }

        // 4) Construction de l'entité via le mapper
        $order = OrderMapper::fromDto(
            $dto,
            $user,
            $productData
        );

        // 5) Persist
        $this->orderStoreRepository->save($order);

        return $order;
    }

    /**
     * @throws AccessDeniedException if order not found or not owned by user
     * @throws DomainException       if cutoff is passed
     */
    public function editOrder(int $orderId, OrderCreateDto $dto, User $user): Order
    {
        $order = $this->orderStoreRepository->findOneByIdAndUser($orderId, $user);

        if (!$order) {
            throw new AccessDeniedException('Order not found or unauthorized.');
        }

        // Revert old stock & clear lines
        foreach ($order->getProductOrders() as $oldLine) {
            $this->stockService
                ->increaseStock($oldLine->getProduct()->getId(), $oldLine->getQuantity());
            $order->removeProductOrder($oldLine);
        }

        // Nouvelle date de pickup
        $newPickupDate = $dto->pickupDate
            ->setTimezone(new DateTimeZone('Europe/Paris'));

        $this->assertWithinOrderWindow($newPickupDate);
        $order->setPickupDate($newPickupDate);

        // Recréer les lignes et recalculer le total
        $total = 0;
        foreach ($dto->items as $item) {
            $product = $this->stockService
                ->checkAndDecreaseStock($item->productId, $item->quantity);

            $line = new ProductOrder();
            $line
                ->setOrder($order)
                ->setProduct($product)
                ->setQuantity($item->quantity)
                ->setUnitPrice($product->getPrice());

            $order->addProductOrder($line);
            $total += $item->quantity * $product->getPrice();
        }
        $order->setTotal($total);

        $this->orderStoreRepository->save($order);

        return $order;
    }

    /**
     * Ensure order (now) is before the cutoff: the day before pickup at 21:00
     *
     * @throws DomainException
     */
    private function assertWithinOrderWindow(DateTimeImmutable $pickupDate): void
    {
        $tz     = new DateTimeZone('Europe/Paris');
        $pickup = $pickupDate->setTimezone($tz);

        // Cutoff = veille à 21h
        $cutoff = $pickup
            ->modify('-1 day')
            ->setTime(21, 0, 0);

        $now = new DateTimeImmutable('now', $tz);
        if ($now > $cutoff) {
            throw new DomainException(
                'It is too late to place or modify an order for this pickup date.'
            );
        }
    }
}
