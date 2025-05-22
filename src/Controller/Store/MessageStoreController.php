<?php

namespace App\Controller\Store;

use App\Service\Store\MessageStoreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/messages', name: 'store_messages_')]
class MessageStoreController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(MessageStoreService $service): JsonResponse
    {
        $messages = $service->getActiveMessages();

        return $this->json($messages);
    }
}
