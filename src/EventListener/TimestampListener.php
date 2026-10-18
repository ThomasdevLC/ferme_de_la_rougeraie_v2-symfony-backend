<?php

namespace App\EventListener;


use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;

/**
 *  listener sets the createdAt and updatedAt fields of the Product entity
 * before persisting or updating.
 */
#[AsEntityListener(event: 'prePersist', method: 'prePersist', entity: Product::class)]
#[AsEntityListener(event: 'preUpdate', method: 'preUpdate', entity: Product::class)]
class TimestampListener
{
    public function prePersist(Product $product, PrePersistEventArgs $event): void
    {
        $now = new \DateTimeImmutable();

        if (null === $product->getCreatedAt()) {
            $product->setCreatedAt($now);
        }

        $product->setUpdatedAt(new \DateTimeImmutable());
    }

    public function preUpdate(Product $product, PreUpdateEventArgs $event): void
    {
        $product->setUpdatedAt(new \DateTimeImmutable());
    }
}
