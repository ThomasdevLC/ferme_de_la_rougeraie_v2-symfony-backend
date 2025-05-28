<?php

namespace App\Dto\User;

use Symfony\Component\Security\Core\Validator\Constraints as SecurityAssert;
use Symfony\Component\Validator\Constraints as Assert;

class UserProfileUpdateDto
{
    public ?string $oldPhone = null;

    #[Assert\NotBlank(message: 'Le téléphone est requis.', groups: ['phone_update'])]
    public ?string $phone = null;

    #[SecurityAssert\UserPassword(
        message: 'Le mot de passe actuel est incorrect',
        groups: ['password_update']
    )]
    public ?string $oldPassword = null;

    #[Assert\When(
        expression: "this.plainPassword !== null",
        constraints: [
            new Assert\NotBlank([
                'message' => 'Veuillez entrer un nouveau mot de passe',
            ]),
            new Assert\Length([
                'min'        => 8,
                'max'        => 4096,
                'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères',
            ]),
            new Assert\Regex([
                'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).+$/',
                'message' => 'Le mot de passe doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial.',
            ]),
        ],
        groups: ['password_update']
    )]
    public ?string $plainPassword = null;
}
