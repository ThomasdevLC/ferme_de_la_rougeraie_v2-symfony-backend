<?php

namespace App\Tests\Unit\Mapper;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Enum\ProductUnit;
use App\Mapper\ProductMapper;
use App\Utils\Translator\UnitTranslator;
use PHPUnit\Framework\TestCase;

class ProductMapperTest extends TestCase
{
    public function testSimpleProductHasPriceAndNoVariants(): void
    {
        $product = (new Product())
            ->setName('Aillet')
            ->setPriceInEuros(2.2)
            ->setUnit(ProductUnit::BUNDLE)
            ->setImage('aillet.jpg');
        $this->setId($product, 65);

        $dto = ProductMapper::toDto($product, new UnitTranslator());

        $this->assertFalse($dto->hasVariants);
        $this->assertSame(2.2, $dto->price);
        $this->assertSame([], $dto->variants);
    }

    public function testVariantProductHasNullPriceAndOnlyDisplayedVariants(): void
    {
        $product = (new Product())
            ->setName('Concombres')
            ->setUnit(ProductUnit::PIECE)
            ->setImage('concombre.jpg')
            ->setHasVariants(true);
        $this->setId($product, 253);

        $product->addVariant($this->makeVariant(1, 'Petit', 2.0, true));
        $product->addVariant($this->makeVariant(2, 'Moyen', 3.0, true));
        $product->addVariant($this->makeVariant(3, 'Caché', 4.0, false));

        $dto = ProductMapper::toDto($product, new UnitTranslator());

        $this->assertTrue($dto->hasVariants);
        // A variant product never exposes a product-level price.
        $this->assertNull($dto->price);
        // Only displayed variants are mapped (the hidden one is excluded).
        $this->assertCount(2, $dto->variants);
        $this->assertSame(['Petit', 'Moyen'], array_map(fn($v) => $v->label, $dto->variants));
        $this->assertSame(2.0, $dto->variants[0]->price);
    }

    private function makeVariant(int $id, string $label, float $priceEuros, bool $displayed): ProductVariant
    {
        $variant = (new ProductVariant())
            ->setLabel($label)
            ->setPriceInEuros($priceEuros)
            ->setStock(5)
            ->setIsDisplayed($displayed);
        $this->setId($variant, $id);

        return $variant;
    }

    private function setId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
