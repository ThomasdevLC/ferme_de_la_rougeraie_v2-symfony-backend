<?php

namespace App\Service\Store;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Repository\Admin\ProductRepository;
use App\Repository\Admin\ProductVariantRepository;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;

class StockStoreService
{
    public function __construct(
        private ProductRepository $productRepository,
        private ProductVariantRepository $variantRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Check stock for one product.
     *
     * @throws \DomainException
     */
    public function checkAndDecreaseStock(int $productId, float $quantity): Product
    {
        if ($quantity <= 0) {
            throw new DomainException('La quantité doit être supérieure à zéro.');
        }

        $product = $this->productRepository->find($productId);

        if (!$product || !$product->isDisplayed() || $product->isDeleted()) {
            throw new \DomainException("Produit non disponible");
        }

        if ($product->hasVariants()) {
            throw new \DomainException("Ce produit nécessite le choix d'une déclinaison.");
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

    /**
     * Check and decrease the stock of a single variant.
     *
     * @throws DomainException
     */
    public function checkAndDecreaseVariantStock(int $variantId, float $quantity): ProductVariant
    {
        if ($quantity <= 0) {
            throw new DomainException('La quantité doit être supérieure à zéro.');
        }

        $variant = $this->variantRepository->find($variantId);

        if (!$variant || !$variant->isDisplayed()) {
            throw new DomainException('Déclinaison non disponible.');
        }

        $product = $variant->getProduct();
        if (!$product || !$product->isDisplayed() || $product->isDeleted()) {
            throw new DomainException('Produit non disponible.');
        }

        // stock null = untracked (unlimited): no check, no decrement.
        if ($variant->getStock() !== null) {
            if (!$variant->canDecrementStock($quantity)) {
                throw new DomainException(
                    "Stock insuffisant pour {$product->getName()} — {$variant->getLabel()}. Quantité restante : {$variant->getStock()}."
                );
            }

            $variant->decrementStock($quantity);
        }

        return $variant;
    }

    public function increaseVariantStock(int $variantId, float $qty): void
    {
        $variant = $this->variantRepository->find($variantId);
        if (!$variant) {
            throw new DomainException("Déclinaison #{$variantId} introuvable.");
        }

        // Untracked stock: nothing to restore.
        if ($variant->getStock() === null) {
            return;
        }

        $newStock = $variant->getStock() + $qty;
        $variant->setStock($newStock);

        if ($newStock > 0) {
            $variant->setIsDisplayed(true);
        }
    }
}
