<?php

namespace App\Dto\User;

use App\Utils\Validator\StrongPassword;
use Symfony\Component\Validator\Constraints as Assert;

class UserProfileUpdateDto
{
    public ?string $oldPhone = null;
    #[Assert\NotBlank(message: 'Le téléphone est requis.')]
    public string $phone;

    public ?string $oldPassword = null;
    #[StrongPassword]
    public ?string $plainPassword = null;
}
