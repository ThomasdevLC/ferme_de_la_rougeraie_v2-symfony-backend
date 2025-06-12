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
     * Returns a distinct list of product IDs for orders
     *  that are not deleted and match the given pickup day.
     *
     * @return int[]
     */
    public function getProductIdsByPickupDay(PickupDay $pickupDay): array
    {
        $rows = $this->createQueryBuilder('po')
            ->select('DISTINCT IDENTITY(po.product) AS productId')
            ->innerJoin('po.order', 'o')
            ->andWhere('o.isDeleted = false')
            ->andWhere('o.pickup = :pickupDay')
            ->setParameter('pickupDay', $pickupDay)
            ->getQuery()
            ->getArrayResult();

        return array_map(fn(array $r) => (int) $r['productId'], $rows);
    }

    /**
     * Returns the calculated product quantities ordered by each user
     * for a specific pickup day  for non-deleted orders.
     */
    public function getUserProductQuantitiesByPickupDay(PickupDay $pickupDay): array


    {
        return $this->createQueryBuilder('po')
            ->select(
                'IDENTITY(o.user)    AS userId',
                'IDENTITY(po.product) AS productId',
                'SUM(po.quantity)     AS totalQuantity'
            )
            ->innerJoin('po.order', 'o')
            ->andWhere('o.isDeleted = false')
            ->andWhere('o.pickup = :pickupDay')
            ->setParameter('pickupDay', $pickupDay)
            ->groupBy('userId', 'productId')
            ->getQuery()
            ->getArrayResult();
    }
}
