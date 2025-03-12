<?php

namespace App\Entity;

use App\Enum\ProductUnit;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\Column(enumType: ProductUnit::class)]
    private ?ProductUnit $unit = null;

    #[ORM\Column(nullable: true)]
    private ?float $inter = null;

    #[ORM\Column]
    private ?bool $isDisplayed = null;

    #[ORM\Column]
    private ?bool $hasStock = null;

    #[ORM\Column(nullable: true)]
    private ?int $stock = null;

    #[ORM\Column]
    private ?bool $limited = null;

    #[ORM\Column]
    private ?bool $discount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $discountText = null;

    #[ORM\Column(length: 255)]
    private ?string $image = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, ProductOrder>
     */
    #[ORM\OneToMany(targetEntity: ProductOrder::class, mappedBy: 'product')]
    private Collection $productOrders;

    public function __construct()
    {
        $this->productOrders = new ArrayCollection();
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getUnit(): ?ProductUnit
    {
        return $this->unit;
    }

    public function setUnit(ProductUnit $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getInter(): ?float
    {
        return $this->inter;
    }

    public function setInter(?float $inter): static
    {
        $this->inter = $inter;

        return $this;
    }

    public function isDisplayed(): ?bool
    {
        return $this->isDisplayed;
    }

    public function setIsDisplayed(bool $isDisplayed): static
    {
        $this->isDisplayed = $isDisplayed;

        return $this;
    }


    public function hasStock(): ?bool
    {
        return $this->hasStock;
    }

    public function setHasStock(bool $hasStock): static
    {
        $this->hasStock = $hasStock;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function isLimited(): ?bool
    {
        return $this->limited;
    }

    public function setLimited(bool $limited): static
    {
        $this->limited = $limited;

        return $this;
    }

    public function isDiscount(): ?bool
    {
        return $this->discount;
    }

    public function setDiscount(bool $discount): static
    {
        $this->discount = $discount;

        return $this;
    }


    public function getDiscountText(): ?string
    {
        return $this->discountText;
    }

    public function setDiscountText(?string $discountText): static
    {
        $this->discountText = $discountText;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, ProductOrder>
     */
    public function getProductOrders(): Collection
    {
        return $this->productOrders;
    }

    public function addProductOrder(ProductOrder $productOrder): static
    {
        if (!$this->productOrders->contains($productOrder)) {
            $this->productOrders->add($productOrder);
            $productOrder->setProduct($this);
        }

        return $this;
    }

    public function removeProductOrder(ProductOrder $productOrder): static
    {
        if ($this->productOrders->removeElement($productOrder)) {
            // set the owning side to null (unless already changed)
            if ($productOrder->getProduct() === $this) {
                $productOrder->setProduct(null);
            }
        }

        return $this;
    }

}
