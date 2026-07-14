<?php

namespace App\Tests\Unit\Command;

use App\Command\BackfillProductCategoriesCommand;
use App\Enum\ProductCategory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BackfillProductCategoriesCommandTest extends TestCase
{
    /**
     * @dataProvider nameCases
     */
    public function testCategorize(string $name, ?ProductCategory $expected): void
    {
        $command = new BackfillProductCategoriesCommand($this->createMock(EntityManagerInterface::class));

        $method = new \ReflectionMethod($command, 'categorize');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($command, $name));
    }

    /**
     * @return iterable<string, array{0: string, 1: ?ProductCategory}>
     */
    public static function nameCases(): iterable
    {
        // Straightforward matches.
        yield 'lemon is a fruit' => ['Citron', ProductCategory::FRUIT];
        yield 'young garlic is a vegetable' => ['Aillet', ProductCategory::VEGETABLE];
        yield 'infusion' => ["Boucle D'or Tisane Vitalité", ProductCategory::INFUSION];
        yield 'chicken rillette is prepared food' => ['Rillette De Poulet 200G', ProductCategory::PREPARED_FOOD];
        yield 'walnuts are grocery' => ['Noix', ProductCategory::GROCERY];
        yield 'egg is dairy_egg' => ['Oeuf Buléon Ab', ProductCategory::DAIRY_EGG];
        yield 'milk is dairy_egg' => ['Lait (Apportez Votre Contenant)', ProductCategory::DAIRY_EGG];

        // Ordering traps: a more specific rule must win.
        yield 'apple juice is a drink, not a fruit' => ['Jus De Pomme Guéhenno', ProductCategory::DRINK];
        yield 'kiwi honey is a jam, not a fruit' => ['Miel De Kiwi', ProductCategory::HONEY_JAM];
        yield 'strawberry jam is a jam, not a fruit' => ['650G De Confiture De Fraises', ProductCategory::HONEY_JAM];

        // "pomme de terre" is a vegetable, a plain apple is a fruit.
        yield 'potato is a vegetable' => ['Nouvelles Pommes De Terre Agata', ProductCategory::VEGETABLE];
        yield 'potato (singular) is a vegetable' => ['Monalisa Chair Tendre Pomme De Terre', ProductCategory::VEGETABLE];
        yield 'apple variety is a fruit' => ['Pommes Braedurn Ab Bzh', ProductCategory::FRUIT];

        // "lait" must not swallow "laitue".
        yield 'lettuce is a vegetable, not dairy' => ['Laitue Sucrine', ProductCategory::VEGETABLE];

        // Genuinely ambiguous / junk stay unclassified.
        yield 'test product is unmatched' => ['Test', null];
        yield 'unnamed promo is unmatched' => ['Promo 3 Barquettes', null];
    }
}
