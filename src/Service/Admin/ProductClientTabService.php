<?php

namespace App\Service\Admin;

use App\Dto\Product\ProductAdminDto;
use App\Repository\Admin\ProductOrderRepository;
use App\Repository\Admin\UserRepository;

class ProductClientTabService
{
    public function __construct(
        private ProductOrderRepository $productOrderRepository,
        private ProductService         $productService,
        private UserRepository         $userRepository
    ) {}

    /**
     * Gets the ordered lines for a given pickup weekday (one line per
     * product/variant), the users who placed orders, and a quantity grid.
     *
     * @param int $weekday The selected pickup weekday (1 = Monday … 7 = Sunday)
     *
     * @return array{
     *     0: array<int, array{key: string, productName: string, unit: string, variantLabel: ?string}>,
     *     1: User[],
     *     2: array<int, array<string, float>>
     * }
     */
    public function getProductClientQuantitiesByWeekday(int $weekday): array
    {
        $rows = $this->productOrderRepository
            ->getUserVariantQuantitiesByPickupDay($weekday);

        $productIds = array_values(array_unique(
            array_map(fn(array $row) => (int) $row['productId'], $rows)
        ));

        $productById = [];
        foreach ($this->productService->getProductAdminDtosByIds($productIds) as $dto) {
            $productById[$dto->id] = $dto;
        }

        [$lines, $quantitiesTab] = $this->buildLinesAndQuantities($rows, $productById);

        $userIds = array_keys($quantitiesTab);
        $users   = $userIds
            ? $this->userRepository->findUsersByIds($userIds)
            : [];

        return [$lines, $users, $quantitiesTab];
    }

    /**
     * Turns raw rows into display lines (one per product/variant, keyed by
     * "productId-variantId") and a quantity grid [userId][lineKey] => qty.
     *
     * @param array<array{userId: int|string, productId: int|string, variantId: int|string|null, variantLabel: ?string, totalQuantity: string}> $rows
     * @param array<int, ProductAdminDto> $productById
     * @return array{0: array<int, array{key: string, productName: string, unit: string, variantLabel: ?string}>, 1: array<int, array<string, float>>}
     */
    private function buildLinesAndQuantities(array $rows, array $productById): array
    {
        $lines = [];
        $quantitiesTab = [];

        foreach ($rows as $row) {
            $userId    = (int) $row['userId'];
            $productId = (int) $row['productId'];
            $variantId = $row['variantId'] !== null ? (int) $row['variantId'] : null;
            $key       = $productId . '-' . ($variantId ?? 0);

            if (!isset($lines[$key])) {
                $dto = $productById[$productId] ?? null;
                $lines[$key] = [
                    'key'          => $key,
                    'productName'  => $dto?->name ?? '',
                    'unit'         => $dto?->unit ?? '',
                    'variantLabel' => $row['variantLabel'] ?? null,
                ];
            }

            $quantitiesTab[$userId][$key] = (float) $row['totalQuantity'];
        }

        // Group variants under their product, alphabetically.
        uasort($lines, static fn(array $a, array $b): int =>
            [$a['productName'], $a['variantLabel'] ?? '']
            <=> [$b['productName'], $b['variantLabel'] ?? '']
        );

        return [array_values($lines), $quantitiesTab];
    }
}
