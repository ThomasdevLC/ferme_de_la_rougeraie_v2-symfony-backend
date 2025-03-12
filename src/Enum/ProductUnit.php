<?php

namespace App\Enum;

enum ProductUnit: string {
    case PIECE = 'PIECE';
    case BUNDLE = 'BUNDLE';
    case BUNCH = 'BUNCH';
    case LITER = 'LITER';
    case KG = 'KG';
}
