<?php

namespace App\Entity;

use App\Repository\Admin\BasketItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BasketItemRepository::class)]
class BasketItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * The basket this line belongs to (a Product with isBasket = true).
     */
    #[ORM\ManyToOne(inversedBy: 'basketItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $basket = null;

    /**
     * The component product listed in the assortment.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private ?float $quantity = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function __toString(): string
    {
        return $this->product?->getName() ?? 'Composant';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBasket(): ?Product
    {
        return $this->basket;
    }

    public function setBasket(?Product $basket): static
    {
        $this->basket = $basket;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
