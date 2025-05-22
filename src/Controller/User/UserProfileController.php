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
        ValidatorInterface $validator
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $dto = new UserProfileUpdateDto();
        $data = json_decode($request->getContent(), true);

        $dto->phone = $data['phone'] ?? '';
        $dto->plainPassword = $data['plainPassword'] ?? null;

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $mapper->updateUserFromDto($user, $dto);
        $em->flush();

        return $this->json(['message' => 'Profil mis à jour avec succès']);
    }
}
