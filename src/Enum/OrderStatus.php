<?php

namespace App\Enum;

enum OrderStatus: string {
    case PENDING = 'PENDING';
    case DONE = 'DONE';
}
