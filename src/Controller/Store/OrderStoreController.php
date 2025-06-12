<?php

namespace App\Controller\Store;

use App\Dto\Order\Create\CartItemDto;
use App\Dto\Order\Create\OrderCreateDto;
use App\Mapper\OrderMapper;
use App\Service\Store\OrderStoreService;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/orders', name: 'store_orders_')]
class OrderStoreController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(OrderStoreService $orderStoreService): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $orderDtos = $orderStoreService->getOrdersForUser($user);

        $response = new JsonResponse();
        $response->setEncodingOptions(
            $response->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION
        );
        $response->setData($orderDtos);

        return $response;
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request, OrderStoreService $orderStoreService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['items']) || empty($data['pickupDate'])) {
            return $this->json(['error' => 'Champs manquants.'], 400);
        }

        try {
            // 1) Convertir la date reçue en DateTimeImmutable
            $pickupDate = new \DateTimeImmutable(
                $data['pickupDate'],
                new \DateTimeZone('Europe/Paris')
            );

            // 2) Construire le DTO avec l'objet DateTimeImmutable
            $dto = new OrderCreateDto(
                items: array_map(
                    fn(array $item) => new CartItemDto(
                        productId: $item['productId'],
                        quantity:  $item['quantity']
                    ),
                    $data['items']
                ),
                pickupDate: $pickupDate
            );

            // 3) Créer la commande
            $order = $orderStoreService->createOrderFromCart($dto, $user);

            return $this->json([
                'success' => true,
                'orderId' => $order->getId(),
            ], 201);

        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);

        } catch (\Exception $e) {
            // Attraper ici les erreurs de DateTimeImmutable et autres imprévues
            return $this->json(['error' => 'Données invalides.'], 400);
        }
    }

    #[Route('/{id}', name: 'edit', methods: ['PUT'])]
    public function edit(
        int $id,
        Request $request,
        OrderStoreService $orderStoreService
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data['items']) || empty($data['pickup'])) {
            return $this->json(['error' => 'Champs manquants.'], 400);
        }

        $dto = new OrderCreateDto(
            items: array_map(
                fn(array $item) => new CartItemDto($item['productId'], $item['quantity']),
                $data['items']
            ),
            pickupDate: $data['pickupDate']
        );

        try {
            $order       = $orderStoreService->editOrder($id, $dto, $user);
            $orderDto    = OrderMapper::toDto($order);

            $response = new JsonResponse();
            $response->setEncodingOptions(
                $response->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION
            );
            $response->setData($orderDto);

            return $response;
        } catch (AccessDeniedException $e) {
            return $this->json(['error' => $e->getMessage()], 403);
        } catch (DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }


}
