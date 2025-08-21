<?php

namespace App\Repository\Admin;

use App\Entity\Product;
use App\Enum\PickupDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }


    /**
     * Find products by a list of IDs.
     *
     * @param int[] $ids
     * @return Product[]
     */
    public function findProductsByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }


    /**
     * @return int[] Get product IDs that can be deleted (not linked to orders).
     */
    public function findDeletableProductIds(array $ids): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->leftJoin('p.productOrders', 'po')
            ->leftJoin('po.order', 'o')
            ->where('p.id IN (:ids)')
            ->andWhere('o.isDeleted = true OR o.id IS NULL')
            ->setParameter('ids', $ids)
            ->groupBy('p.id');

        return array_column($qb->getQuery()->getScalarResult(), 'id');
    }

    /**
     * @return string[] Get product names that can not be deleted ( linked to orders)
     */
    public function findNonDeletableProductNames(array $ids): array
    {
        return array_column(
            $this->createQueryBuilder('p')
                ->select('DISTINCT p.name')
                ->join('p.productOrders', 'po')
                ->join('po.order', 'o')
                ->where('p.id IN (:ids)')
                ->andWhere('o.isDeleted = false')
                ->setParameter('ids', $ids)
                ->getQuery()
                ->getScalarResult(),
            'name'
        );
    }


    /**
     * Soft delete products by IDs.
     *
     * @param int[] $ids
     * @return int Number of affected rows
     */

    public function softDeleteProductsByIds(array $ids): int
    {
        return $this->createQueryBuilder('p')
            ->update()
            ->set('p.isDeleted', ':true')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->setParameter('true', true)
            ->getQuery()
            ->execute();
    }

    /**
     * @return string[] Get names of products that are sold out.
     */
    public function findSoldOutProductNames(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.name AS name')
            ->where('p.isDeleted = false')
            ->andWhere('p.hasStock = true')
            ->andWhere('p.stock <= 0')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'name');
    }

}
