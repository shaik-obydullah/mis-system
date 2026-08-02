<?php

namespace App\Entity;

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

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $sku = null;

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductCategory $category = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $price = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $cost = '0.00';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $currentStock = '0.000';

    #[ORM\Column(type: 'decimal', precision: 12, scale: 3)]
    private string $lowStockThreshold = '5.000';

    #[ORM\ManyToOne(inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Supplier $supplier = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: OrderItem::class)]
    private Collection $orderItems;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: PurchaseItem::class)]
    private Collection $purchaseItems;

    public function __construct()
    {
        $this->orderItems = new ArrayCollection();
        $this->purchaseItems = new ArrayCollection();
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

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getCategory(): ?ProductCategory
    {
        return $this->category;
    }

    public function setCategory(?ProductCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getCost(): string
    {
        return $this->cost;
    }

    public function setCost(string $cost): static
    {
        $this->cost = $cost;

        return $this;
    }

    public function getCurrentStock(): string
    {
        return $this->currentStock;
    }

    public function setCurrentStock(string $currentStock): static
    {
        $this->currentStock = $currentStock;

        return $this;
    }

    public function addStock(string $qty): static
    {
        $this->currentStock = (string) (float) $this->currentStock + (float) $qty;

        return $this;
    }

    public function reduceStock(string $qty): static
    {
        $this->currentStock = (string) max(0, (float) $this->currentStock - (float) $qty);

        return $this;
    }

    public function isLowStock(): bool
    {
        return (float) $this->currentStock <= (float) $this->lowStockThreshold;
    }

    public function getLowStockThreshold(): string
    {
        return $this->lowStockThreshold;
    }

    public function setLowStockThreshold(string $lowStockThreshold): static
    {
        $this->lowStockThreshold = $lowStockThreshold;

        return $this;
    }

    public function getSupplier(): ?Supplier
    {
        return $this->supplier;
    }

    public function setSupplier(?Supplier $supplier): static
    {
        $this->supplier = $supplier;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getOrderItems(): Collection
    {
        return $this->orderItems;
    }

    /**
     * @return Collection<int, PurchaseItem>
     */
    public function getPurchaseItems(): Collection
    {
        return $this->purchaseItems;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
