<?php

namespace App\Command;

use App\Entity\Attendance;
use App\Entity\Employee;
use App\Entity\Expense;
use App\Entity\ExpenseCategory;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\SalaryRecord;
use App\Entity\Supplier;
use App\Entity\User;
use App\Service\OrderService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Loads sample data for development')]
class SeedCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private OrderService $orderService,
        private SettingsService $settings,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding sample data');

        $admin = $this->user('admin@mis.test', 'admin123', 'System Admin', 'ROLE_ADMIN');
        $manager = $this->user('manager@mis.test', 'manager123', 'Maria Lopez', 'ROLE_MANAGER');
        $staff = $this->user('staff@mis.test', 'staff123', 'John Carter', 'ROLE_STAFF');

        $this->settings->set('business_name', 'Golden Fork Restaurant');
        $this->settings->set('currency', 'USD');
        $this->settings->set('timezone', 'UTC');
        $this->settings->set('tax_rate', '5');

        $food = $this->category('Food');
        $beverages = $this->category('Beverages');
        $desserts = $this->category('Desserts');
        $supplies = $this->category('Kitchen Supplies');

        $rent = $this->expenseCategory('Rent');
        $utilities = $this->expenseCategory('Utilities');
        $marketing = $this->expenseCategory('Marketing');
        $maintenance = $this->expenseCategory('Maintenance');
        $misc = $this->expenseCategory('Miscellaneous');

        $farm = $this->supplier('Fresh Farm Produce', 'Ahmed Haddad', '+1-555-0101', 'orders@freshfarm.example', '12 Market St');
        $ocean = $this->supplier('Ocean Foods Co.', 'Sara Khalil', '+1-555-0102', 'sales@oceanfoods.example', '88 Harbor Rd');
        $bev = $this->supplier('Beverage Distributors Inc.', 'David Chen', '+1-555-0103', 'info@beveragedist.example', '240 Industrial Ave');

        $products = [
            ['Margherita Pizza', 'PIZ-001', $food, $farm, '12.50', '5.00', '120', '10', true],
            ['Pepperoni Pizza', 'PIZ-002', $food, $farm, '15.00', '6.50', '80', '10', true],
            ['Caesar Salad', 'SAL-001', $food, $farm, '9.00', '3.20', '60', '8', true],
            ['Grilled Salmon', 'SEA-001', $food, $ocean, '18.50', '9.00', '40', '8', true],
            ['Chicken Biryani', 'RIC-001', $food, $farm, '13.00', '5.50', '75', '10', true],
            ['Pasta Alfredo', 'PAS-001', $food, $farm, '12.00', '4.80', '55', '8', true],
            ['Cola 500ml', 'BEV-001', $beverages, $bev, '2.50', '0.80', '300', '50', true],
            ['Fresh Orange Juice', 'BEV-002', $beverages, $bev, '4.50', '1.50', '90', '20', true],
            ['Mineral Water', 'BEV-003', $beverages, $bev, '1.50', '0.40', '400', '60', true],
            ['Chocolate Lava Cake', 'DES-001', $desserts, $farm, '6.00', '2.00', '45', '10', true],
            ['Tiramisu', 'DES-002', $desserts, $farm, '7.00', '2.50', '30', '8', true],
            ['Olive Oil (1L)', 'SUP-001', $supplies, $ocean, '12.00', '7.00', '30', '10', true],
        ];

        $productEntities = [];
        foreach ($products as $row) {
            $productEntities[] = $this->product(...$row);
        }

        $employeeRows = [
            ['Liam Brown', 'chef', '+1-555-0201', '2800.00', true],
            ['Emma Wilson', 'waiter', '+1-555-0202', '1800.00', true],
            ['Noah Garcia', 'cashier', '+1-555-0203', '1600.00', true],
            ['Olivia Martinez', 'manager', '+1-555-0204', '3200.00', true],
            ['Ethan Lee', 'staff', '+1-555-0205', '1500.00', true],
        ];

        $employees = [];
        foreach ($employeeRows as $row) {
            $employees[] = $this->employee(...$row);
        }

        $this->em->flush();
        $io->text(sprintf('%d users, %d products, %d employees ready.', 3, count($productEntities), count($employees)));

        $this->seedExpenses($admin, [$rent, $utilities, $marketing, $maintenance, $misc]);
        $this->seedAttendance($employees);
        $this->seedSalaries($manager, $employees);

        $ordersCreated = $this->seedOrders($admin, $productEntities);

        $this->em->flush();

        $io->success(sprintf('Seed complete. Created %d orders.', $ordersCreated));
        $io->note('Logins: admin@mis.test / admin123 | manager@mis.test / manager123 | staff@mis.test / staff123');

        return Command::SUCCESS;
    }

    private function user(string $email, string $password, string $name, string $role): User
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user) {
            return $user;
        }

        $user = (new User())
            ->setEmail($email)
            ->setFullName($name)
            ->setRole($role)
            ->setActive(true);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);

        return $user;
    }

    private function category(string $name): ProductCategory
    {
        $category = $this->em->getRepository(ProductCategory::class)->findOneBy(['name' => $name]);
        if ($category) {
            return $category;
        }

        $category = (new ProductCategory())->setName($name);
        $this->em->persist($category);

        return $category;
    }

    private function expenseCategory(string $name): ExpenseCategory
    {
        $category = $this->em->getRepository(ExpenseCategory::class)->findOneBy(['name' => $name]);
        if ($category) {
            return $category;
        }

        $category = (new ExpenseCategory())->setName($name);
        $this->em->persist($category);

        return $category;
    }

    private function supplier(string $name, ?string $contact, ?string $phone, ?string $email, ?string $address): Supplier
    {
        $supplier = $this->em->getRepository(Supplier::class)->findOneBy(['name' => $name]);
        if ($supplier) {
            return $supplier;
        }

        $supplier = (new Supplier())
            ->setName($name)
            ->setContactPerson($contact)
            ->setPhone($phone)
            ->setEmail($email)
            ->setAddress($address);
        $this->em->persist($supplier);

        return $supplier;
    }

    private function product(string $name, string $sku, ProductCategory $category, Supplier $supplier, string $price, string $cost, string $stock, string $threshold, bool $active): Product
    {
        $product = $this->em->getRepository(Product::class)->findOneBy(['sku' => $sku]);
        if ($product) {
            return $product;
        }

        $product = (new Product())
            ->setName($name)
            ->setSku($sku)
            ->setCategory($category)
            ->setSupplier($supplier)
            ->setPrice($price)
            ->setCost($cost)
            ->setCurrentStock($stock)
            ->setLowStockThreshold($threshold)
            ->setActive($active);
        $this->em->persist($product);

        return $product;
    }

    private function employee(string $name, string $role, ?string $phone, string $salary, bool $active): Employee
    {
        $employee = $this->em->getRepository(Employee::class)->findOneBy(['name' => $name]);
        if ($employee) {
            return $employee;
        }

        $employee = (new Employee())
            ->setName($name)
            ->setRole($role)
            ->setPhone($phone)
            ->setBaseSalary($salary)
            ->setHireDate(new \DateTimeImmutable('-2 years'))
            ->setActive($active);
        $this->em->persist($employee);

        return $employee;
    }

    private function seedExpenses(User $user, array $categories): void
    {
        $count = $this->em->getRepository(Expense::class)->count([]);
        if ($count > 0) {
            return;
        }

        $now = new \DateTimeImmutable();
        $seed = [
            ['Rent', 'Monthly rent', 3500.00],
            ['Utilities', 'Electricity & water', 420.75],
            ['Marketing', 'Social media ads', 250.00],
            ['Maintenance', 'AC repair', 180.00],
            ['Miscellaneous', 'Cleaning supplies', 95.50],
            ['Utilities', 'Internet', 120.00],
        ];

        foreach ($seed as $i => $row) {
            $day = min($now->format('d'), (string) (($i % 20) + 1));
            $category = $this->em->getRepository(ExpenseCategory::class)->findOneBy(['name' => $row[0]]);

            $expense = (new Expense())
                ->setAmount((string) $row[2])
                ->setCategory($category)
                ->setDescription($row[1])
                ->setDate(new \DateTimeImmutable($now->format('Y-m-') . str_pad($day, 2, '0', STR_PAD_LEFT) . ' 12:00:00'))
                ->setCreatedBy($user);
            $this->em->persist($expense);
        }
    }

    private function seedAttendance(array $employees): void
    {
        $count = $this->em->getRepository(Attendance::class)->count([]);
        if ($count > 0) {
            return;
        }

        $now = new \DateTimeImmutable();
        $statuses = ['present', 'present', 'present', 'present', 'late', 'absent', 'leave'];
        $period = $now->format('Y-m');
        $days = (int) $now->format('j');

        for ($day = 1; $day <= $days; ++$day) {
            $date = new \DateTime($period . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT) . ' 00:00:00');
            $weekend = (int) $date->format('N') >= 6;

            foreach ($employees as $i => $employee) {
                if ($weekend) {
                    $status = 'leave';
                } else {
                    $status = $statuses[($i + $day) % count($statuses)];
                }

                $record = (new Attendance())
                    ->setEmployee($employee)
                    ->setDate($date)
                    ->setStatus($status);
                $this->em->persist($record);
            }
        }
    }

    private function seedSalaries(User $user, array $employees): void
    {
        $period = (new \DateTimeImmutable())->format('Y-m');
        $count = $this->em->getRepository(SalaryRecord::class)->count([]);
        if ($count > 0) {
            return;
        }

        foreach ($employees as $employee) {
            $record = (new SalaryRecord())
                ->setEmployee($employee)
                ->setPeriod($period)
                ->setAmount($employee->getBaseSalary())
                ->setPaidAt(new \DateTimeImmutable())
                ->setCreatedBy($user);
            $this->em->persist($record);
        }
    }

    private function seedOrders(User $user, array $products): int
    {
        $count = $this->em->getRepository(Order::class)->count([]);
        if ($count > 0) {
            return 0;
        }

        $customers = ['Walk-in customer', 'Ahmed', 'Sara', 'David', 'Fatima', 'Omar', 'Lina', 'Carlos', 'Maya'];
        $taxRate = 0.05;
        $created = 0;

        for ($dayOffset = 13; $dayOffset >= 0; --$dayOffset) {
            $ordersToday = 3 + (($dayOffset * 7) % 5);
            $baseTime = (new \DateTimeImmutable())->modify("-{$dayOffset} days")->setTime(10, 0, 0);

            for ($i = 0; $i < $ordersToday; ++$i) {
                $createdAt = $baseTime->modify(sprintf('+%d minutes', ($i * 83) % 480));

                $lineCount = 1 + (($i + $dayOffset) % 3);
                $picked = [];
                for ($l = 0; $l < $lineCount; ++$l) {
                    $product = $products[($i * 3 + $dayOffset * 2 + $l) % count($products)];
                    $qty = 1 + (($i + $l) % 3);
                    $picked[$product->getId()] = ['product' => $product, 'quantity' => $qty];
                }

                try {
                    $order = $this->orderService->createOrder(
                        $user,
                        array_values($picked),
                        $customers[($i + $dayOffset) % count($customers)],
                        $taxRate,
                        $createdAt,
                    );
                } catch (\InvalidArgumentException) {
                    continue;
                }

                $roll = ($i + $dayOffset) % 10;
                if ($roll < 7) {
                    $this->orderService->markPaid($order, [Order::METHOD_CASH, Order::METHOD_CARD, Order::METHOD_ONLINE][($i + $dayOffset) % 3]);
                } elseif ($roll < 8) {
                    $this->orderService->cancel($order);
                } else {
                    $this->orderService->markPaid($order, Order::METHOD_CASH);
                    $this->orderService->refund($order);
                }

                ++$created;
            }
        }

        return $created;
    }
}
