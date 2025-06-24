<?php

namespace App\Entity;

use App\Repository\Admin\ProductOrderRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductOrderRepository::class)]
class ProductOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'float')]
    private ?float $quantity = null;

    #[ORM\Column]
    private ?int $unitPrice = null;

    #[ORM\ManyToOne(inversedBy: 'productOrders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(
        targetEntity: Order::class,
        inversedBy: 'productOrders'
    )]
    #[ORM\JoinColumn(
        name: 'order_id',
        referencedColumnName: 'id',
        nullable: false
    )]
    private ?Order $order = null;

    public function getId(): ?int
    {
        return $this->id;
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


    public function getUnitPrice(): ?int
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(int $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

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

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    /**
     * @param Order|null $order
     */
    public function setOrder(?Order $order): static
    {
        $this->order = $order;
        return $this;
    }
}
