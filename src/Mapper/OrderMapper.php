<?php

namespace App\Mapper;

use App\Dto\Order\Create\OrderCreateDto;
use App\Dto\Order\Display\OrderDetailsDto;
use App\Dto\Order\Display\OrderItemDto;
use App\Dto\Product\ProductDto;
use App\Entity\Order;
use App\Entity\ProductOrder;
use App\Entity\User;
use App\Enum\ProductUnit;

class OrderMapper
{
    public  function toDto(Order $order): OrderDetailsDto
    {

        $items = [];
        foreach ($order->getProductOrders() as $po) {
            $prod = $po->getProduct();
            $unitPriceEuros = $po->getUnitPrice() / 100;

            $imagePath = '/uploads/images/' . $prod->getImage();

            $unitLabel = match ($prod->getUnit()) {
                ProductUnit::KG     => 'Kilo',
                ProductUnit::LITER  => 'Litre',
                ProductUnit::PIECE  => 'Pièce',
                ProductUnit::BUNDLE => 'Bouquet',
                ProductUnit::BUNCH  => 'Botte',
            };

            $productDto = new ProductDto(
                id: $prod->getId(),
                name: $prod->getName(),
                price: $prod->hasVariants() ? null : round($prod->getPrice() / 100, 2),
                unit:         $unitLabel,
                image:        $imagePath,
                hasStock:    $prod->hasStock(),
                stock: $prod->getStock(),
                limited: (bool) $prod->isLimited(),
                discount: (bool) $prod->isDiscount(),
                discountText: $prod->getDiscountText(),
                inter: $prod->getInter(),
                hasVariants: $prod->hasVariants(),
                variants: [],
            );
            $qty = (float) $po->getQuantity();
            $variant = $po->getProductVariant();

            if ($variant !== null) {
                $availableStock = $variant->getStock() !== null
                    ? $variant->getStock() + $qty
                    : null;
            } elseif ($productDto->hasStock && $productDto->stock !== null) {
                $availableStock = $productDto->stock + $qty;
            } else {
                $availableStock = null;
            }

            $items[] = new OrderItemDto(
                product:   $productDto,
                quantity:  $qty,
                unitPrice: round($unitPriceEuros, 2),
                lineTotal: round($unitPriceEuros * $qty, 2),
                availableStock: $availableStock,
                variantLabel: $variant?->getLabel()
            );

        }

        return new OrderDetailsDto(
            id:         $order->getId(),
            total:      round($order->getTotal() / 100, 2),
            pickupDate: $order->getPickupDate(),
            pickupDay:  $order->getPickupDay(),
            createdAt:  $order->getCreatedAt(),
            done:       $order->isDone(),
            isEditable:  $order->isEditable(),
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
    public  function fromDto(
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
            $total += $this->appendLine($order, $entry);
        }
        $order->setTotal($total);


        return $order;
    }

    public function updateFromDto(
        OrderCreateDto $dto,
        Order $order,
        array $productData
    ): void {

        $total = 0;
        foreach ($productData as $entry) {
            $total += $this->appendLine($order, $entry);
        }

        $order->setTotal($total);
    }

    /**
     * Build one order line from a productData entry, attach it to the order,
     * and return its line total (cents). Freezes the variant price when the
     * line targets a variant, otherwise the product price.
     *
     * @param array{product: \App\Entity\Product, variant?: ?\App\Entity\ProductVariant, quantity: float} $entry
     */
    private function appendLine(Order $order, array $entry): int
    {
        $product  = $entry['product'];
        $variant  = $entry['variant'] ?? null;
        $quantity = $entry['quantity'];

        $unitPrice = $variant !== null ? $variant->getPrice() : $product->getPrice();

        $po = (new ProductOrder())
            ->setOrder($order)
            ->setProduct($product)
            ->setProductVariant($variant)
            ->setQuantity($quantity)
            ->setUnitPrice($unitPrice);
        $order->addProductOrder($po);

        return (int) round($unitPrice * $quantity);
    }


}
