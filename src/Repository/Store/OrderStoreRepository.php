<?php

namespace App\Repository\Store;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;


class OrderStoreRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $em;

    public function __construct(
        ManagerRegistry $registry,
        EntityManagerInterface $em
    ) {
        parent::__construct($registry, Order::class);
        $this->em = $em;
    }

    /**
     * @return Order[]
     */
    public function findOrdersByUser(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->andWhere('o.cancelledAt IS NULL')
            ->setParameter('user', $user)
            ->leftJoin('o.productOrders', 'po')
            ->addSelect('po')
            ->leftJoin('po.product', 'p')
            ->addSelect('p')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByIdAndUser(int $orderId, User $user): ?Order
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->andWhere('o.user = :user')
            ->andWhere('o.cancelledAt IS NULL')
            ->setParameter('id', $orderId)
            ->setParameter('user', $user)
            ->leftJoin('o.productOrders', 'po')
            ->addSelect('po')
            ->leftJoin('po.product', 'p')
            ->addSelect('p')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Same lookup as findOneByIdAndUser, but locks the order row
     * (SELECT ... FOR UPDATE) so two concurrent edit/cancel requests
     * cannot both restore its stock. Must run inside a transaction.
     * No fetch-join: only the order row is locked, lines load lazily.
     */
    public function findOneByIdAndUserForUpdate(int $orderId, User $user): ?Order
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->andWhere('o.user = :user')
            ->andWhere('o.cancelledAt IS NULL')
            ->setParameter('id', $orderId)
            ->setParameter('user', $user)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }



    public function save(Order $order, bool $flush = true): void
    {
        $this->em->persist($order);
        if ($flush) {
            $this->em->flush();
        }
    }


    public function remove(Order $order, bool $flush = true): void
    {
        $this->em->remove($order);
        if ($flush) {
            $this->em->flush();
        }
    }
}
