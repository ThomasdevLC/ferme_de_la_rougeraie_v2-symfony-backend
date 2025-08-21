<?php

namespace App\Entity;

use App\Attribute\AutoTitleCase;
use App\Enum\ProductUnit;
use App\Repository\Admin\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\EntityListeners(['App\EventListener\TitleCaseListener'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[AutoTitleCase]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\Column(nullable: false, enumType: ProductUnit::class )]
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

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;


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
        $this->isDisplayed = false;
        $this->hasStock = false;
        $this->limited = false;
        $this->discount = false;
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

    //  Prices in euros ( EasyAdmin )
    public function getPriceInEuros(): ?float
    {
        return $this->price !== null ? $this->price / 100 : null;
    }

    public function setPriceInEuros(float $price): static
    {
        $this->price = (int) round($price * 100);
        return $this;
    }

    public function getUnit(): ?ProductUnit
    {
        return $this->unit;
    }

    public function setUnit(ProductUnit $unit): static
    {
        $this->unit = $unit;

        if ($unit !== ProductUnit::KG) {
            $this->inter = null;
        }

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

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }


    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
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
            if ($productOrder->getProduct() === $this) {
                $productOrder->setProduct(null);
            }
        }

        return $this;
    }


    public function canDecrementStock(int $quantity): bool
    {
        return $this->hasStock()
            && $this->stock !== null
            && $this->stock >= $quantity;
    }

    public function decrementStock(int $quantity): void
    {

        $this->stock -= $quantity;
        if ($this->stock <= 0) {
            $this->isDisplayed = false;
        }
    }

    public function getSoldOutLabel(): string
    {
        return ($this->hasStock && $this->stock !== null && $this->stock <= 0)
            ? '❌ Épuisé'
            : '✔ Disponible';
    }


    #[Assert\Callback]
    public function validateProductRequirements(ExecutionContextInterface $context): void
    {
        if ($this->hasStock && ($this->stock === null || $this->stock < 0)) {
            $context->buildViolation('Le stock est requis ')
                ->atPath('stock')
                ->addViolation();
        }

        if ($this->unit === ProductUnit::KG && ($this->inter === null || $this->inter <= 0)) {
            $context->buildViolation('L\'intervalle est requis pour un produit vendu au kilo.')
                ->atPath('inter')
                ->addViolation();
        }

    }



}
