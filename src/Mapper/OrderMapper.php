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
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
class OrderMapper
{
    private string $uploadsBase;

    public function __construct(ParameterBagInterface $params)
    {
        $this->uploadsBase = rtrim($params->get('uploads_path'), '/') . '/';
    }
    public  function toDto(Order $order): OrderDetailsDto
    {

        $items = [];
        foreach ($order->getProductOrders() as $po) {
            $prod = $po->getProduct();
            $unitPriceEuros = $po->getUnitPrice() / 100;

            $imagePath = $this->uploadsBase . ltrim($prod->getImage(), '/');

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
                price: round($prod->getPrice() / 100, 2),
                unit:         $unitLabel,
                image:        $imagePath,
                stock: $prod->getStock(),
                limited: (bool) $prod->isLimited(),
                discount: (bool) $prod->isDiscount(),
                discountText: $prod->getDiscountText(),
                inter: $prod->getInter()
            );

            $items[] = new OrderItemDto(
                product:   $productDto,
                quantity:  (float) $po->getQuantity(),
                unitPrice: round($unitPriceEuros, 2),
                lineTotal: round($unitPriceEuros * $po->getQuantity(), 2)
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
            $product  = $entry['product'];
            $quantity = $entry['quantity'];

            $unitPrice = $product->getPrice();

            $lineTotal = (int) round($unitPrice * $quantity);

            $po = new ProductOrder();
            $po
                ->setOrder($order)
                ->setProduct($product)
                ->setQuantity($quantity)
                ->setUnitPrice($unitPrice);
            $order->addProductOrder($po);

            $total += $lineTotal;
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
            $product   = $entry['product'];
            $quantity  = $entry['quantity'];
            $unitPrice = $product->getPrice();

            $po = (new ProductOrder())
                ->setOrder($order)
                ->setProduct($product)
                ->setQuantity($quantity)
                ->setUnitPrice($unitPrice);

            $order->addProductOrder($po);
            $total += (int) round($unitPrice * $quantity);
        }

        $order->setTotal($total);
    }


}
