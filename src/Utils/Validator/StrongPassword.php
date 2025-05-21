<?php

namespace App\Utils\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute]
class StrongPassword extends Constraint
{
public string $message = 'Le mot de passe doit contenir au moins une minuscule, une majuscule, un chiffre et un caractère spécial, et faire au moins 8 caractères.';
}
