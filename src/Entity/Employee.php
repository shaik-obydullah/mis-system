<?php

namespace App\Entity;

use App\Repository\EmployeeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
class Employee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private string $role = 'staff';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $baseSalary = '0.00';

    #[ORM\Column]
    private \DateTimeImmutable $hireDate;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\OneToMany(mappedBy: 'employee', targetEntity: Attendance::class)]
    private Collection $attendances;

    #[ORM\OneToMany(mappedBy: 'employee', targetEntity: SalaryRecord::class)]
    private Collection $salaryRecords;

    public function __construct()
    {
        $this->hireDate = new \DateTimeImmutable();
        $this->attendances = new ArrayCollection();
        $this->salaryRecords = new ArrayCollection();
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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getBaseSalary(): string
    {
        return $this->baseSalary;
    }

    public function setBaseSalary(string $baseSalary): static
    {
        $this->baseSalary = $baseSalary;

        return $this;
    }

    public function getHireDate(): \DateTimeImmutable
    {
        return $this->hireDate;
    }

    public function setHireDate(\DateTimeImmutable $hireDate): static
    {
        $this->hireDate = $hireDate;

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
     * @return Collection<int, Attendance>
     */
    public function getAttendances(): Collection
    {
        return $this->attendances;
    }

    /**
     * @return Collection<int, SalaryRecord>
     */
    public function getSalaryRecords(): Collection
    {
        return $this->salaryRecords;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
