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
        private ProductService $productService,
        private UserRepository $userRepository
    ) {}

    /**
     * Gets products ordered for a given pickup day, the users who placed orders,
     * and a quantity mapping by user and product.
     *
     * @param PickupDay $pickupDay The selected pickup day (Tuesday or FRIDAY).
     *
     * @return array{
     *     0: Product[],                   // List of Products ordered on that day
     *     1: User[],                      // List of Users who placed orders
     *     2: array<int, array<int, int>>  // Quantities: [userId][productId] => ordered quantity
     * }
     */
    public function getProductClientQuantities(PickupDay $pickupDay): array
    {
        // 1) Fetch product IDs that have at least one order on the selected pickup day
        $productIds = $this->productOrderRepository
            ->getProductIdsByPickupDay($pickupDay);

        // 2) Load the corresponding Product entities
        $products = $this->productService
            ->getProductAdminDtosByIds($productIds);

        // 3) Fetch  quantity data
        $rawQuantities = $this->productOrderRepository
            ->getUserProductQuantitiesByPickupDay($pickupDay);

        // 4) Build the [userId][productId] => quantity grid
        $quantitiesTab = $this->buildQuantitiesTab($rawQuantities);

        // 5) Load the Users associated with the orders
        $userIds = array_keys($quantitiesTab);
        $users = $userIds
            ? $this->userRepository->findUsersByIds($userIds)
            : [];

        return [$products, $users, $quantitiesTab];
    }

    /**
     * Converts raw query results into a structured array [userId][productId] => quantity.
     *
     * @param array<array{userId: int, productId: int, totalQuantity: string}> $rawData
     *     The raw data retrieved from the database.
     *
     * @return array<int, array<int, int>>
     *     Structured format: [userId][productId] = ordered quantity
     */
    private function buildQuantitiesTab(array $rawData): array
    {
        $tab = [];
        foreach ($rawData as $row) {
            $userId = (int) $row['userId'];
            $productId = (int) $row['productId'];
            $qty = (int) $row['totalQuantity'];
            $tab[$userId][$productId] = $qty;
        }
        return $tab;
    }
}
