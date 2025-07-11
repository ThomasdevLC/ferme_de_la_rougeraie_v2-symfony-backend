<?php

namespace App\Service\Admin;

use App\Enum\PickupDay;
use App\Repository\Admin\ProductOrderRepository;
use App\Repository\Admin\UserRepository;
use App\Entity\Product;

class ProductClientTabService
{
    public function __construct(
        private ProductOrderRepository $productOrderRepository,
        private ProductService         $productService,
        private UserRepository         $userRepository
    ) {}

    /**
     * Gets products ordered for a given pickup weekday,
     * the users who placed orders, and a quantity mapping.
     *
     * @param int $weekday The selected pickup weekday (1 = Monday … 7 = Sunday)
     *
     * @return array{
     *     0: Product[],                   // Products ordered on that day
     *     1: User[],                      // Users who placed orders
     *     2: array<int, array<int, int>>  // Quantities grid [userId][productId] => qty
     * }
     */
    public function getProductClientQuantitiesByWeekday(int $weekday): array
    {
        // 1) IDs de produits commandés ce jour
        $productIds = $this->productOrderRepository
            ->getProductIdsByPickupDay($weekday);

        // 2) Entités Product pour l’admin
        $products = $this->productService
            ->getProductAdminDtosByIds($productIds);

        // 3) Quantités brutes [userId, productId, totalQuantity]
        $rawQuantities = $this->productOrderRepository
            ->getUserProductQuantitiesByPickupDay($weekday);

        // 4) Construction de la grille [userId][productId] => quantité
        $quantitiesTab = $this->buildQuantitiesTab($rawQuantities);

        // 5) Chargement des utilisateurs
        $userIds = array_keys($quantitiesTab);
        $users   = $userIds
            ? $this->userRepository->findUsersByIds($userIds)
            : [];

        return [$products, $users, $quantitiesTab];
    }

    /**
     * Converts raw query results into a structured array [userId][productId] => quantity.
     *
     * @param array<array{userId: int, productId: int, totalQuantity: string}> $rawData
     * @return array<int, array<int, int>>
     */
    private function buildQuantitiesTab(array $rawData): array
    {
        $tab = [];
        foreach ($rawData as $row) {
            $userId    = (int) $row['userId'];
            $productId = (int) $row['productId'];
            $qty       = (int) $row['totalQuantity'];
            $tab[$userId][$productId] = $qty;
        }
        return $tab;
    }
}