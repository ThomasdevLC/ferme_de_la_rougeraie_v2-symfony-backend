<?php
namespace App\Dto\User;

class UserProfileDto
{
    public function __construct(
        public readonly int    $id,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $phone,
    )
    {}
}
