<?php

namespace App\Tests\Unit\Service;

use App\Dto\Product\ProductAdminDto;
use App\Repository\Admin\ProductOrderRepository;
use App\Repository\Admin\UserRepository;
use App\Service\Admin\ProductClientTabService;
use App\Service\Admin\ProductService;
use PHPUnit\Framework\TestCase;

class ProductClientTabServiceTest extends TestCase
{
    public function testVariantsBecomeDistinctLinesWithCompositeKeys(): void
    {
        $rows = [
            ['userId' => 10, 'productId' => 253, 'variantId' => 1,    'variantLabel' => 'Petit', 'totalQuantity' => '3'],
            ['userId' => 10, 'productId' => 253, 'variantId' => 3,    'variantLabel' => 'Gros',  'totalQuantity' => '3'],
            ['userId' => 10, 'productId' => 99,  'variantId' => null, 'variantLabel' => null,    'totalQuantity' => '1'],
        ];

        $orderRepo = $this->createMock(ProductOrderRepository::class);
        $orderRepo->method('getUserVariantQuantitiesByPickupDay')->willReturn($rows);

        $productService = $this->createMock(ProductService::class);
        $productService->method('getProductAdminDtosByIds')->willReturn([
            new ProductAdminDto(253, 'Concombres', 'Pièce', true, false),
            new ProductAdminDto(99, 'Miel de kiwi', 'Pièce', true, false),
        ]);

        $userRepo = $this->createMock(UserRepository::class);
        $userRepo->method('findUsersByIds')->willReturn([]);

        $service = new ProductClientTabService($orderRepo, $productService, $userRepo);

        [$lines, , $grid] = $service->getProductClientQuantitiesByWeekday(2);

        // One line per variant + one for the simple product.
        $this->assertCount(3, $lines);
        // Grouped by product then variant label: Gros, Petit, then the simple product.
        $this->assertSame(['253-3', '253-1', '99-0'], array_column($lines, 'key'));
        $this->assertSame('Concombres', $lines[0]['productName']);
        $this->assertSame('Gros', $lines[0]['variantLabel']);
        $this->assertNull($lines[2]['variantLabel']);

        // Quantities are keyed by the composite line key, per user.
        $this->assertSame(3.0, $grid[10]['253-1']);
        $this->assertSame(3.0, $grid[10]['253-3']);
        $this->assertSame(1.0, $grid[10]['99-0']);
    }
}
