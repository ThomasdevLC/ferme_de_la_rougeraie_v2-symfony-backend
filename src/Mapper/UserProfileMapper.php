<?php
namespace App\Mapper;
use App\Dto\User\UserProfileDto;

use App\Entity\User;

final class UserProfileMapper
{
    public function toUserProfileDto(User $user): UserProfileDto
    {
        return new UserProfileDto(
            id: $user->getId(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            phone: $user->getPhone()
        );
    }
}