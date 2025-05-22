<?php

namespace App\Controller\Store;

use App\Service\Store\ProductStoreService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/products', name: 'store_products_')]
class ProductStoreController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(ProductStoreService $productStoreService): JsonResponse
    {
        $products = $productStoreService->getAvailableProductsForFront();

        return $this->json($products);
    }
}
