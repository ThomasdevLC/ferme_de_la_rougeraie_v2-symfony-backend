<?php

namespace App\Mapper;

use App\Dto\Order\Create\OrderCreateDto;
use App\Dto\Order\Display\OrderDetailsDto;
use App\Dto\Order\Display\OrderItemDto;
use App\Entity\Order;
use App\Entity\ProductOrder;
use App\Entity\User;

class OrderMapper
{
    public static function toDto(Order $order): OrderDetailsDto
    {
        $items = [];
        foreach ($order->getProductOrders() as $po) {
            $unitPriceEuros = $po->getUnitPrice() / 100;
            $items[] = new OrderItemDto(
                productName: $po->getProduct()->getName(),
                quantity:    (float) $po->getQuantity(),
                unitPrice:   round($unitPriceEuros, 2),
                lineTotal:   round($unitPriceEuros * $po->getQuantity(), 2)
            );
        }

        return new OrderDetailsDto(
            id:         $order->getId(),
            total:      round($order->getTotal() / 100, 2),
            pickupDate: $order->getPickupDate(),
            pickupDay:  $order->getPickupDay(),
            createdAt:  $order->getCreatedAt(),
            done:       $order->isDone(),
            items:      $items
        );
    }

    /**
     * Builds an Order entity from the creation DTO.
     *
     * @param OrderCreateDto        $dto         Contains the list of cart items and the desired pickup date
     * @param User                  $user        The customer placing the order
     * @param array<string,mixed>[] $productData Array of ['product' => Product, 'quantity' => int]
     */
    public static function fromDto(
        OrderCreateDto $dto,
        User $user,
        array $productData
    ): Order {
        $order = new Order();
        $order
            ->setUser($user)
            ->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))
            ->setPickupDate($dto->pickupDate)
            ->setDone(false);

        $total = 0;
        foreach ($productData as $entry) {
            $product  = $entry['product'];
            $quantity = $entry['quantity'];

            $po = new ProductOrder();
            $po
                ->setOrder($order)
                ->setProduct($product)
                ->setQuantity($quantity)
                ->setUnitPrice($product->getPrice());

            $order->addProductOrder($po);

            $total += $quantity * $product->getPrice();
        }

        $order->setTotal($total);

        return $order;
    }
}
