<?php

namespace App\Service;

use App\Entity\Expense;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

class ReportService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * @return array<string, string|int>
     */
    public function dashboard(\DateTimeImmutable $date = new \DateTimeImmutable('today')): array
    {
        $dayFrom = $date->setTime(0, 0, 0);
        $dayTo = $dayFrom->modify('+1 day');
        $monthFrom = $date->modify('first day of this month')->setTime(0, 0, 0);
        $monthTo = $monthFrom->modify('+1 month');

        $orders = $this->em->getRepository(Order::class);
        $expenses = $this->em->getRepository(Expense::class);

        $today = $orders->salesSummary($dayFrom, $dayTo);
        $month = $orders->salesSummary($monthFrom, $monthTo);

        $todayExpenses = $expenses->totalForRange($dayFrom, $dayTo);
        $monthExpenses = $expenses->totalForRange($monthFrom, $monthTo);

        $todayCogs = $this->cogsForRange($dayFrom, $dayTo);
        $monthCogs = $this->cogsForRange($monthFrom, $monthTo);

        return [
            'today_sales' => $today['total'],
            'today_orders' => $today['count'],
            'today_expenses' => $todayExpenses,
            'today_profit' => $this->money((float) $today['total'] - (float) $todayCogs - (float) $todayExpenses),
            'month_sales' => $month['total'],
            'month_orders' => $month['count'],
            'month_expenses' => $monthExpenses,
            'month_profit' => $this->money((float) $month['total'] - (float) $monthCogs - (float) $monthExpenses),
            'cogs' => $monthCogs,
            'low_stock_count' => count($this->em->getRepository(Product::class)->findLowStock()),
        ];
    }

    public function cogsForRange(\DateTimeImmutable $from, \DateTimeImmutable $to): string
    {
        $row = $this->em->getRepository(OrderItem::class)
            ->createQueryBuilder('oi')
            ->select('COALESCE(SUM(oi.quantity * p.cost), 0) AS cogs')
            ->join('oi.order', 'o')
            ->join('oi.product', 'p')
            ->where('o.status = :status')
            ->andWhere('o.createdAt >= :from')
            ->andWhere('o.createdAt < :to')
            ->setParameter('status', Order::STATUS_PAID)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        return (string) $row['cogs'];
    }

    /**
     * @return array<int, array{name: string, sku: string, stock: float, cost: string, value: float}>
     */
    public function inventoryValuation(): array
    {
        $products = $this->em->getRepository(Product::class)->findAll();
        $rows = [];

        foreach ($products as $product) {
            $stock = (float) $product->getCurrentStock();
            $rows[] = [
                'name' => (string) $product->getName(),
                'sku' => (string) $product->getSku(),
                'stock' => $stock,
                'cost' => $product->getCost(),
                'value' => round($stock * (float) $product->getCost(), 2),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return $rows;
    }

    /**
     * @return array<string, string|int|array>
     */
    public function profitAndLoss(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $sales = $this->em->getRepository(Order::class)->salesSummary($from, $to);
        $cogs = $this->cogsForRange($from, $to);
        $expenses = $this->em->getRepository(Expense::class)->totalForRange($from, $to);

        $gross = (float) $sales['total'] - (float) $cogs;
        $net = $gross - (float) $expenses;

        return [
            'sales' => $sales['total'],
            'orders' => $sales['count'],
            'cogs' => $cogs,
            'gross_profit' => $this->money($gross),
            'expenses' => $expenses,
            'net_profit' => $this->money($net),
        ];
    }

    private function money(float $value): string
    {
        return (string) round($value, 2);
    }
}
