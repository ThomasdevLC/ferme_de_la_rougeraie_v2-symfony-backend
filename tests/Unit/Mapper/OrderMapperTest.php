<?php

namespace App\Tests\Unit\Mapper;

use App\Dto\Order\Create\OrderCreateDto;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductOrder;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Enum\ProductUnit;
use App\Mapper\OrderMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class OrderMapperTest extends TestCase
{
    public function testFromDtoFreezesVariantPriceAndReferencesVariant(): void
    {
        $product = (new Product())->setName('Concombre')->setHasVariants(true);
        $variant = (new ProductVariant())->setLabel('Gros')->setPrice(180);
        $simple  = (new Product())->setName('Aillet')->setPrice(250);

        $productData = [
            ['product' => $product, 'variant' => $variant, 'quantity' => 2.0],
            ['product' => $simple,  'variant' => null,     'quantity' => 1.0],
        ];

        $dto = new OrderCreateDto(
            items: [],
            pickupDate: new DateTimeImmutable('+3 days'),
        );

        $order = (new OrderMapper())->fromDto($dto, new User(), $productData);

        $lines = array_values($order->getProductOrders()->toArray());
        $this->assertCount(2, $lines);

        // Variant line: references the variant and freezes its price.
        $this->assertSame($variant, $lines[0]->getProductVariant());
        $this->assertSame($product, $lines[0]->getProduct());
        $this->assertSame(180, $lines[0]->getUnitPrice());

        // Simple line: no variant, product price.
        $this->assertNull($lines[1]->getProductVariant());
        $this->assertSame(250, $lines[1]->getUnitPrice());

        // Total = 180*2 + 250*1
        $this->assertSame(610, $order->getTotal());
    }

    public function testToDtoExposesVariantLabelAndVariantStock(): void
    {
        $product = (new Product())
            ->setName('Concombre')
            ->setUnit(ProductUnit::PIECE)
            ->setImage('concombre.jpg')
            ->setHasVariants(true);
        $this->setId($product, 253);

        $variant = (new ProductVariant())
            ->setLabel('Gros')
            ->setPrice(180)
            ->setStock(10)
            ->setIsDisplayed(true);

        $order = (new Order())
            ->setTotal(360)
            ->setCreatedAt(new DateTimeImmutable())
            ->setPickupDate(new DateTimeImmutable('+3 days'));
        $this->setId($order, 1);

        $order->addProductOrder(
            (new ProductOrder())
                ->setProduct($product)
                ->setProductVariant($variant)
                ->setQuantity(2)
                ->setUnitPrice(180)
        );

        $dto = (new OrderMapper())->toDto($order);
        $item = $dto->items[0];

        $this->assertSame('Gros', $item->variantLabel);
        $this->assertSame(1.8, $item->unitPrice);
        // Variant product exposes no product-level price.
        $this->assertNull($item->product->price);
        // Available stock is the variant's (10) plus the ordered quantity (2).
        $this->assertSame(12.0, $item->availableStock);
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
