<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }


    /**
     * Récupère les utilisateurs ayant passé au moins une commande non supprimée.
     *
     * @return User[] Renvoie un tableau d'entités User
     */
    public function findUsersWithNonDeletedOrders(): array
    {
        return $this->createQueryBuilder('u')
            ->join('u.orders', 'o')
            ->andWhere('o.isDeleted = false')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les utilisateurs role : ROLE_USER .
     *
     * @return User[] Renvoie un tableau d'entités User
     */

    public function findAllNonAdminUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles NOT LIKE :adminRole')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();
    }

}
