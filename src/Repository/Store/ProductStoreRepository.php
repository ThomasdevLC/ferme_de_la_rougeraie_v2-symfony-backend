<?php

namespace App\Repository\Store;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ProductStoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * Get displayed products.
     *
     * @return Product[]
     */
    public function findDisplayedAvailableProducts(): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect("CASE WHEN SUBSTRING(p.name, 1, 1) >= '0' AND SUBSTRING(p.name, 1, 1) <= '9' THEN 1 ELSE 0 END AS HIDDEN nameOrder")
            ->where('p.isDisplayed = true')
            ->andWhere('p.isDeleted = false')
            ->orderBy('nameOrder', 'ASC')
            ->addOrderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }


}
