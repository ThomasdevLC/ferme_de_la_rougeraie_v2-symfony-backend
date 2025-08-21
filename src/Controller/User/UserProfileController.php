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
use Symfony\Component\Validator\ConstraintViolation;
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

    #[Route('/api/me', name: 'api_me_update', methods: ['PATCH'])]
    public function update(
        Request                     $request,
        UserProfileUpdateMapper     $mapper,
        EntityManagerInterface      $em,
        ValidatorInterface          $validator,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $dto = new UserProfileUpdateDto();
        $dto->oldPassword = $data['oldPassword'] ?? null;
        $dto->plainPassword = $data['plainPassword'] ?? null;
        $dto->oldPhone = $data['oldPhone'] ?? null;
        $dto->phone = $data['phone'] ?? null;

        $groups = ['Default'];
        if (null !== $dto->plainPassword) {
            $groups[] = 'password_update';
        }
        if (null !== $dto->phone) {
            $groups[] = 'phone_update';
        }

        $errors = $validator->validate($dto, null, $groups);
        $formatted = [];
        /** @var ConstraintViolation $violation */
        foreach ($errors as $violation) {
            $field = $violation->getPropertyPath();
            $formatted[$field][] = $violation->getMessage();
        }

        if (null !== $dto->phone) {
            if (null === $dto->oldPhone || $dto->oldPhone !== $user->getPhone()) {
                $formatted['oldPhone'][] = 'Ancien numéro de téléphone incorrect.';
            }
        }

        if (count($formatted) > 0) {
            return $this->json(['errors' => $formatted], 422);
        }

        if (null !== $dto->plainPassword) {
            $user->setPassword(
                $passwordHasher->hashPassword($user, $dto->plainPassword)
            );
        }

        if (null !== $dto->phone && $dto->phone !== $user->getPhone()) {
            $user->setPhone($dto->phone);
        }

        $mapper->updateUserFromDto($user, $dto);
        $em->flush();

        return $this->json(['message' => 'Profil mis à jour']);
    }

    #[Route('/api/me', name: 'api_me_delete', methods: ['DELETE'])]
    public function delete(
        EntityManagerInterface $em
    ): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($user === null) {
            return $this->json(['error' => 'Utilisateur non authentifié'], 401);
        }

        $user->setIsDeleted(true);
        $em->flush();

        return $this->json([
            'message' => 'Votre compte a bien été désactivé.'
        ]);
    }


}