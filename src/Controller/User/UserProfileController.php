<?php

namespace App\Controller\User;

use App\Dto\User\UserProfileDto;
use App\Mapper\UserProfileMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class UserProfileController extends AbstractController
{
    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(UserProfileMapper $mapper): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var UserProfileDto $dto */
        $dto = $mapper->toUserProfileDto($user);

        return $this->json($dto);
    }
}




