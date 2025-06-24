<?php

namespace App\Service\Store;

use App\Entity\Product;
use App\Repository\Admin\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;

class StockStoreService
{
    public function __construct(
        private ProductRepository $productRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Check stock for one product.
     *
     * @throws \DomainException
     */
    public function checkAndDecreaseStock(int $productId, int $quantity): Product
    {
        $product = $this->productRepository->find($productId);

        if (!$product || !$product->isDisplayed() || $product->isDeleted()) {
            throw new \DomainException("Produit non disponible");
        }

        if ($product->hasStock() && !$product->canDecrementStock($quantity)) {
            throw new \DomainException(
                "Stock insuffisant pour le produit : {$product->getName()}. Quantité restante : {$product->getStock()}."
            );
        }

        if ($product->hasStock()) {
            $product->decrementStock($quantity);
        }

        return $product;
    }

    public function increaseStock(int $productId, float $qty): void
    {
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new DomainException("Produit #{$productId} introuvable.");
        }
        $newStock = ($product->getStock() ?? 0) + $qty;
        $product->setStock($newStock);

        if ($newStock > 0) {
            $product->setIsDisplayed(true);
        }

    }
}
