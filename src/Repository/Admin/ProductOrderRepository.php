<?php

namespace App\Repository\Admin;

use App\Enum\PickupDay;
use App\Entity\ProductOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductOrder::class);
    }

    /**
     * Quantities ordered by each user for a pickup day, aggregated by
     * (product, variant) so variant lines stay distinct. Non-deleted,
     * not-yet-done orders only.
     *
     * @return array<array{userId: int|string, productId: int|string, variantId: int|string|null, variantLabel: ?string, totalQuantity: string}>
     */
    public function getUserVariantQuantitiesByPickupDay(int $pickupDay): array
    {
        return $this->createQueryBuilder('po')
            ->select(
                'IDENTITY(o.user)            AS userId',
                'IDENTITY(po.product)        AS productId',
                'IDENTITY(po.productVariant) AS variantId',
                'v.label                     AS variantLabel',
                'SUM(po.quantity)            AS totalQuantity'
            )
            ->innerJoin('po.order', 'o')
            ->leftJoin('po.productVariant', 'v')
            ->andWhere('o.isDeleted = false')
            ->andWhere('o.done = false')
            ->andWhere('o.pickupDay  = :pickupDay')
            ->setParameter('pickupDay', $pickupDay)
            ->groupBy('userId', 'productId', 'variantId', 'variantLabel')
            ->getQuery()
            ->getArrayResult();
    }
}
