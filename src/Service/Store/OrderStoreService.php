<?php

namespace App\Service\Store;

use App\Dto\Order\Create\CartItemDto;
use App\Dto\Order\Create\OrderCreateDto;
use App\Dto\Order\Display\OrderDetailsDto;
use App\Entity\Order;
use App\Entity\User;
use App\Mapper\OrderMapper;
use App\Repository\Store\OrderStoreRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OrderStoreService
{
    public function __construct(
        private OrderStoreRepository $orderStoreRepository,
        private StockStoreService    $stockService,
        private OrderMapper          $mapper,
        private EntityManagerInterface $em
    ) {}

    /**
     * @param User $user
     * @return Order[]
     */
    public function getOrdersForUser(User $user): array
    {
        $orders = $this->orderStoreRepository->findOrdersByUser($user);
        return array_map(
            fn(Order $o) => $this->mapper->toDto($o),
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

        return $this->mapper->toDto($order);
    }



    /**
     * @throws DomainException if cutoff is passed
     */
    public function createOrderFromCart(OrderCreateDto $dto, User $user): Order
    {
        $pickupDate = $dto->pickupDate
            ->setTimezone(new DateTimeZone('Europe/Paris'));

        $this->checkPickupDateWithinWindow($pickupDate);

        $productData = $this->decreaseStockAndBuildProductData($dto->items);

        $order = $this->mapper->fromDto($dto, $user, $productData);

        $this->orderStoreRepository->save($order);

        return $order;
    }

    /**
     * @throws AccessDeniedException if order not found or not owned by user
     * @throws DomainException       if cutoff is passed
     */
    public function editOrder(int $orderId, OrderCreateDto $dto, User $user): OrderDetailsDto
    {
        return $this->em->wrapInTransaction(function () use ($orderId, $dto, $user): OrderDetailsDto {
            $order = $this->findLockedOrderOrFail($orderId, $user);

            if (!$order->isEditable()) {
                throw new DomainException('Cette commande ne peut plus être modifiée.');
            }

            $this->restoreStock($order);
            foreach ($order->getProductOrders()->toArray() as $oldLine) {
                $order->removeProductOrder($oldLine);
            }

            $newPickupDate = $dto->pickupDate
                ->setTimezone(new DateTimeZone('Europe/Paris'));
            $this->checkPickupDateWithinWindow($newPickupDate);
            $order->setPickupDate($newPickupDate);

            $productData = $this->decreaseStockAndBuildProductData($dto->items);

            $this->mapper->updateFromDto($dto, $order, $productData);

            $this->orderStoreRepository->save($order);
            return $this->mapper->toDto($order);
        });
    }

    /**
     * Cancel a customer order: restore its stock, then mark it cancelled and
     * done. Lines and total are kept for the admin history.
     *
     * @throws AccessDeniedException if order not found, not owned by user or already cancelled
     * @throws DomainException       if cutoff is passed
     */
    public function cancelOrder(int $orderId, User $user): void
    {
        $this->em->wrapInTransaction(function () use ($orderId, $user): void {
            $order = $this->findLockedOrderOrFail($orderId, $user);

            if (!$order->isEditable()) {
                throw new DomainException('Cette commande ne peut plus être annulée.');
            }

            $this->restoreStock($order);

            $order->cancel()->setDone(true);

            $this->orderStoreRepository->save($order);
        });
    }

    /**
     * Load the order with a row lock (see findOneByIdAndUserForUpdate).
     * Must be called inside a transaction.
     *
     * @throws AccessDeniedException if order not found, not owned by user or already cancelled
     */
    private function findLockedOrderOrFail(int $orderId, User $user): Order
    {
        $order = $this->orderStoreRepository->findOneByIdAndUserForUpdate($orderId, $user);
        if (! $order) {
            throw new AccessDeniedException('Cette commande n\'existe pas ou vous n\'êtes pas autorisé à la modifier.');
        }

        return $order;
    }

    /**
     * Give back to stock every line of the order: a variant line restores the
     * variant's stock, a simple line the product's. Lines are left untouched.
     */
    private function restoreStock(Order $order): void
    {
        foreach ($order->getProductOrders() as $line) {
            if ($line->getProductVariant() !== null) {
                $this->stockService->increaseVariantStock(
                    $line->getProductVariant()->getId(),
                    $line->getQuantity()
                );
            } else {
                $this->stockService->increaseStock(
                    $line->getProduct()->getId(),
                    $line->getQuantity()
                );
            }
        }
    }

    /**
     * Decrease stock for each cart item and build the productData rows the
     * mapper needs. Branches on variantId: a variant line decrements the
     * variant's stock, a simple line the product's.
     *
     * @param CartItemDto[] $items
     * @return array<int, array{product: \App\Entity\Product, variant: ?\App\Entity\ProductVariant, quantity: float}>
     */
    private function decreaseStockAndBuildProductData(array $items): array
    {
        $productData = [];
        foreach ($items as $item) {
            if ($item->variantId !== null) {
                $variant = $this->stockService
                    ->checkAndDecreaseVariantStock($item->variantId, $item->quantity);

                $productData[] = [
                    'product'  => $variant->getProduct(),
                    'variant'  => $variant,
                    'quantity' => $item->quantity,
                ];
            } else {
                $product = $this->stockService
                    ->checkAndDecreaseStock($item->productId, $item->quantity);

                $productData[] = [
                    'product'  => $product,
                    'variant'  => null,
                    'quantity' => $item->quantity,
                ];
            }
        }

        return $productData;
    }

    /**
     * Ensure order (now) is before the cutoff: the day before pickup at 21:00
     *
     */
    private function checkPickupDateWithinWindow(DateTimeImmutable $pickupDate): void
    {
        $tz     = new DateTimeZone('Europe/Paris');
        $pickup = $pickupDate->setTimezone($tz);

        $cutoff = $pickup
            ->modify('-1 day')
            ->setTime(21, 0, 0);

        $now = new DateTimeImmutable('now', $tz);
        if ($now > $cutoff) {
            throw new DomainException(
                'Vous ne pouvez plus modifier ou créer de commande pour cette date de retrait '
            );
        }
    }
}
