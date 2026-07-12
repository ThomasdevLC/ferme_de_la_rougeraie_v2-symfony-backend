<?php

namespace App\Enum;

enum ProductCategory: string
{
    case VEGETABLE     = 'VEGETABLE';
    case FRUIT         = 'FRUIT';
    case HERB          = 'HERB';
    case DAIRY_EGG     = 'DAIRY_EGG';
    case HONEY_JAM     = 'HONEY_JAM';
    case DRINK         = 'DRINK';
    case INFUSION      = 'INFUSION';
    case GROCERY       = 'GROCERY';
    case PREPARED_FOOD = 'PREPARED_FOOD';

    public function label(): string
    {
        return match ($this) {
            self::VEGETABLE     => 'Légumes',
            self::FRUIT         => 'Fruits',
            self::HERB          => 'Herbes',
            self::DAIRY_EGG     => 'Œufs et lait',
            self::HONEY_JAM     => 'Miels et confitures',
            self::DRINK         => 'Boissons',
            self::INFUSION      => 'Infusions',
            self::GROCERY       => 'Épicerie',
            self::PREPARED_FOOD => 'Produits cuisinés',
        };
    }
}
