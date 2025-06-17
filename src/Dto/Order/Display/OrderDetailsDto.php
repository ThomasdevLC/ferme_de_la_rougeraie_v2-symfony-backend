<?php

namespace App\Dto\Order\Display;

use DateTimeImmutable;

readonly class OrderDetailsDto
{
    /**
     * detailed order information for display to the client
     *
     * @param OrderItemDto[] $items
     */
    public function __construct(
        public int                 $id,
        public float               $total,
        public DateTimeImmutable   $pickupDate,
        public int                 $pickupDay,
        public DateTimeImmutable   $createdAt,
        public bool                $done,
        public bool                $isEditable,
        public array               $items,
    ) {}
}