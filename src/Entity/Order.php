<?php

namespace App\Entity;

use App\Enum\PickupDay;
use App\Repository\Admin\OrderRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\Column]
    private ?int $total = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $pickupDate;

    #[ORM\Column(type: 'smallint')]
    private int $pickupDay;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $done = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;


    /**
     * @var Collection<int, ProductOrder>
     */
    #[ORM\OneToMany(
        targetEntity: ProductOrder::class,
        mappedBy: 'order',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $productOrders;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->productOrders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }


    public function getTotal(): ?int
    {
        return $this->total;
    }

    public function setTotal(int $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPickupDate(): \DateTimeImmutable
    {
        return $this->pickupDate;
    }

    /**
     *
     * @param DateTimeImmutable $pickupDate
     * @return $this
     */
    public function setPickupDate(DateTimeImmutable $pickupDate): static
    {
        $dt = $pickupDate->setTimezone(new DateTimeZone('Europe/Paris'));
        $this->pickupDate = $dt;

        $this->pickupDay = (int) $dt->format('N');

        return $this;
    }



    public function getPickupDay(): int
    {
        return $this->pickupDay;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function setDone(bool $done): static
    {
        $this->done = $done;
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
            $productOrder->setOrder($this);
        }

        return $this;
    }

    public function removeProductOrder(ProductOrder $productOrder): static
    {
        if ($this->productOrders->removeElement($productOrder)) {
            if ($productOrder->getOrder() === $this) {
                $productOrder->setOrder(null);
            }
        }

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
     * Returns true if the order can still be edited,
     * i.e. now is before the day-before-pickup at 21h00 cutoff.
     */
    public function isEditable(): bool
    {
        $tz     = new DateTimeZone('Europe/Paris');
        $pickup = $this->pickupDate->setTimezone($tz);

        $cutoff = $pickup
            ->modify('-1 day')
            ->setTime(21, 0, 0);

        $now = new DateTimeImmutable('now', $tz);

        return $now <= $cutoff;
    }

}
