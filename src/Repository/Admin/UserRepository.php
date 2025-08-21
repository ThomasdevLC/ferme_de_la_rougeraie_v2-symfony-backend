<?php

namespace App\Repository\Admin;

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
     * Get all non-admin users.
     *
     * @return User[] returns an array of User objects
     */

    public function findAllNonAdminUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.roles NOT LIKE :adminRole')
            ->andWhere('u.isDeleted = false')
            ->setParameter('adminRole', '%ROLE_ADMIN%')
            ->getQuery()
            ->getResult();
    }

    public function findUsersByIds(array $userIds): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

}
