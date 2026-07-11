<?php

namespace App\Tests\Unit\Mapper;

use App\Dto\Order\Create\OrderCreateDto;
use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Entity\User;
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
}
