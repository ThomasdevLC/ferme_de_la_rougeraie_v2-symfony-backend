<?php

namespace App\Mapper;

use App\Dto\Order\Create\OrderCreateDto;
use App\Dto\Order\Display\OrderDetailsDto;
use App\Dto\Order\Display\OrderItemDto;
use App\Entity\Order;
use App\Entity\ProductOrder;
use App\Entity\User;
use App\Enum\PickupDay;

class OrderMapper
{

    public static function toDto(Order $order): OrderDetailsDto
    {
        $items = [];

        foreach ($order->getProductOrders() as $po) {
            $product = $po->getProduct();

            $quantity = $po->getQuantity();
            $unitPrice = $po->getUnitPrice() !== null ? $po->getUnitPrice() / 100 : 0.0;

            $items[] = new OrderItemDto(
                productName: $product->getName(),
                quantity: $quantity,
                unitPrice: round($unitPrice, 2),
                lineTotal: round($quantity * $unitPrice, 2)
            );
        }


        return new OrderDetailsDto(
            id: $order->getId(),
            total: round($order->getTotal() !== null ? $order->getTotal() / 100 : 0.0, 2),
            pickup: $order->getPickup()?->value,
            createdAt: $order->getCreatedAt(),
            done: $order->isDone(),
            items: $items
        );
    }

    public static function fromDto(
        OrderCreateDto $orderCreateDto,
        User $user,
        array $productData
    ): Order {
        $order = new Order();
        $order->setUser($user)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setPickup(PickupDay::from($orderCreateDto->pickup))
            ->setDone(false);

        $total = 0;

        foreach ($productData as $entry) {
            $product = $entry['product'];
            $quantity = $entry['quantity'];

            $productOrder = new ProductOrder();
            $productOrder
                ->setProduct($product)
                ->setQuantity($quantity)
                ->setUnitPrice($product->getPrice())
                ->setOrder($order);

            $order->addProductOrder($productOrder);

            $lineTotal = (int) round($quantity * $product->getPrice());
            $total += $lineTotal;
        }

        $order->setTotal($total);

        return $order;
    }

}
