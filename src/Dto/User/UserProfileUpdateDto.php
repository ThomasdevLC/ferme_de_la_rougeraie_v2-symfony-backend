<?php

namespace App\Dto\User;

use App\Utils\Validator\StrongPassword;
use Symfony\Component\Validator\Constraints as Assert;

class UserProfileUpdateDto
{
    #[Assert\NotBlank(message: 'Le téléphone est requis.')]
    public string $phone;

    #[StrongPassword]
    public ?string $plainPassword = null;
}
