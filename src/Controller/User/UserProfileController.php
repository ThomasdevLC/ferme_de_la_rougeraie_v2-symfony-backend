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
use App\Form\UserProfileUpdateFormType;

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
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        $dto = new UserProfileUpdateDto();
        $form = $this->createForm(UserProfileUpdateFormType::class, $dto);
        $form->submit(json_decode($request->getContent(), true));

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->json(['errors' => (string) $form->getErrors(true, false)], 400);
        }

        $mapper->updateUserFromDto($user, $dto);
        $em->flush();

        return $this->json(['message' => 'Profil mis à jour avec succès']);
    }
}










