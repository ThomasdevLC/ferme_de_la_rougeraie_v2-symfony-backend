<?php

namespace App\Tests\Unit\Enum;

use App\Enum\ProductCategory;
use PHPUnit\Framework\TestCase;

class ProductCategoryTest extends TestCase
{
    public function testEveryCaseHasANonEmptyFrenchLabel(): void
    {
        foreach (ProductCategory::cases() as $category) {
            $this->assertNotSame('', $category->label());
        }
    }

    public function testLabels(): void
    {
        $this->assertSame('Légumes', ProductCategory::VEGETABLE->label());
        $this->assertSame('Fruits', ProductCategory::FRUIT->label());
        $this->assertSame('Herbes', ProductCategory::HERB->label());
        $this->assertSame('Œufs et lait', ProductCategory::DAIRY_EGG->label());
        $this->assertSame('Miels et confitures', ProductCategory::HONEY_JAM->label());
        $this->assertSame('Boissons', ProductCategory::DRINK->label());
        $this->assertSame('Infusions', ProductCategory::INFUSION->label());
        $this->assertSame('Épicerie', ProductCategory::GROCERY->label());
        $this->assertSame('Produits cuisinés', ProductCategory::PREPARED_FOOD->label());
    }

    /**
     * The declaration order is the canonical order the API and front rely on
     * to display categories. Lock it so a reorder is a conscious change.
     */
    public function testDeclarationOrderIsCanonical(): void
    {
        $keys = array_map(
            static fn (ProductCategory $category): string => $category->value,
            ProductCategory::cases(),
        );

        $this->assertSame([
            'VEGETABLE',
            'FRUIT',
            'HERB',
            'DAIRY_EGG',
            'HONEY_JAM',
            'DRINK',
            'INFUSION',
            'GROCERY',
            'PREPARED_FOOD',
        ], $keys);
    }
}
