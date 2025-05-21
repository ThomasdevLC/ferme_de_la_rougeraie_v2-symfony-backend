<?php

namespace App\Mapper;

use App\Dto\User\UserProfileUpdateDto;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserProfileUpdateMapper
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function updateUserFromDto(User $user, UserProfileUpdateDto $dto): void
    {
        $user->setPhone($dto->phone);

        if (!empty($dto->plainPassword)) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->plainPassword);
            $user->setPassword($hashedPassword);
        }
    }
}
