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
     * @return int[] Get product IDs that can be deleted (not linked to pending orders).
     */
    public function findDeletableProductIds(array $ids): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->leftJoin('p.productOrders', 'po')
            ->leftJoin('po.order', 'o', 'WITH', 'o.done = false')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('p.id')
            ->having('COUNT(o.id) = 0');

        return array_column($qb->getQuery()->getScalarResult(), 'id');
    }

    /**
     * @return string[] Get product names that can not be deleted (linked to pending orders)
     */
    public function findNonDeletableProductNames(array $ids): array
    {
        return array_column(
            $this->createQueryBuilder('p')
                ->select('DISTINCT p.name')
                ->join('p.productOrders', 'po')
                ->join('po.order', 'o')
                ->where('p.id IN (:ids)')
                ->andWhere('o.done = false')
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
     * @return int[] Get IDs of sold out products.
     */
    public function findSoldOutProductIds(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.isDeleted = false')
            ->andWhere('p.hasStock = true')
            ->andWhere('p.stock <= 0')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'id');
    }

    /**
     * Another non-deleted basket already using this name, or null when the name
     * is free.
     *
     * The comparison is case- and accent-insensitive for free: the schema uses
     * the utf8mb4_unicode_ci collation. This matters because the name is
     * title-cased only later, by the TitleCaseListener.
     *
     * isDisplayed comes back so the caller can tell the admin when the clashing
     * basket is hidden — otherwise the error points at a basket absent from the
     * list, which reads as a phantom.
     *
     * @return array{id: int, isDisplayed: bool}|null
     */
    public function findConflictingBasket(string $name, ?int $excludeId): ?array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.id', 'p.isDisplayed')
            ->where('p.isBasket = true')
            ->andWhere('p.isDeleted = false')
            ->andWhere('p.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1);

        if (null !== $excludeId) {
            $qb->andWhere('p.id != :id')->setParameter('id', $excludeId);
        }

        $row = $qb->getQuery()->getOneOrNullResult();

        return null === $row ? null : ['id' => $row['id'], 'isDisplayed' => (bool) $row['isDisplayed']];
    }

}
