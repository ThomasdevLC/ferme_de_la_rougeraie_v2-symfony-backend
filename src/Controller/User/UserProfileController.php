<?php

namespace App\Controller\User;

use App\Dto\User\UserProfileUpdateDto;
use App\Entity\User;
use App\Mapper\UserProfileMapper;
use App\Mapper\UserProfileUpdateMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserProfileController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(UserProfileMapper $mapper): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $dto = $mapper->toUserProfileDto($user);

        return $this->json($dto);
    }

    #[Route('/api/me', name: 'api_me_update', methods: ['PUT'])]
    public function update(
        Request $request,
        UserProfileUpdateMapper $mapper,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $dto = new UserProfileUpdateDto();
        $data = json_decode($request->getContent(), true);

        // Récupération des champs
        $dto->oldPassword = $data['oldPassword'] ?? null;
        $dto->plainPassword = $data['plainPassword'] ?? null;
        $dto->oldPhone = $data['oldPhone'] ?? null;
        $dto->phone = $data['phone'] ?? '';

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        if ($dto->plainPassword) {
            if (!$dto->oldPassword || !$passwordHasher->isPasswordValid($user, $dto->oldPassword)) {
                return $this->json(['error' => 'Mot de passe actuel incorrect'], 400);
            }
            $user->setPassword($passwordHasher->hashPassword($user, $dto->plainPassword));
        }

        if ($dto->phone && $dto->oldPhone && $user->getPhone() !== $dto->oldPhone) {
            return $this->json(['error' => 'Ancien téléphone incorrect'], 400);
        }

        $mapper->updateUserFromDto($user, $dto);
        $em->flush();

        return $this->json(['message' => 'Profil mis à jour avec succès']);
    }
}
