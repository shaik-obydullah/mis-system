<?php

namespace App\Controller;

use App\Repository\ExpenseRepository;
use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use App\Service\CsvService;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reports')]
class ReportController extends AbstractController
{
    private function dateRange(Request $request): array
    {
        $from = (string) $request->query->get('from', (new \DateTimeImmutable('first day of this month'))->format('Y-m-d'));
        $to = (string) $request->query->get('to', (new \DateTimeImmutable())->format('Y-m-d'));

        return [
            $from,
            $to,
            new \DateTimeImmutable($from . ' 00:00:00'),
            new \DateTimeImmutable($to . ' 23:59:59')->modify('+1 second'),
        ];
    }

    #[Route('', name: 'report_index', methods: ['GET'])]
    public function index(
        Request $request,
        ReportService $reportService,
        OrderRepository $orders,
        ExpenseRepository $expenses,
        OrderItemRepository $orderItems,
    ): Response {
        [$from, $to, $fromDate, $toExclusive] = $this->dateRange($request);

        return $this->render('report/index.html.twig', [
            'from' => $from,
            'to' => $to,
            'report' => $reportService->profitAndLoss($fromDate, $toExclusive),
            'salesByDay' => $orders->salesByDay($fromDate, $toExclusive),
            'salesByCategory' => $orders->salesByCategory($fromDate, $toExclusive),
            'expenseBreakdown' => $expenses->totalsByCategory($fromDate, $toExclusive),
            'topProducts' => $orderItems->topProducts($fromDate, $toExclusive),
            'inventory' => $reportService->inventoryValuation(),
        ]);
    }

    #[Route('/export.csv', name: 'report_export', methods: ['GET'])]
    public function export(
        Request $request,
        ReportService $reportService,
        OrderRepository $orders,
        ExpenseRepository $expenses,
        OrderItemRepository $orderItems,
        CsvService $csv,
    ): Response {
        [$from, $to, $fromDate, $toExclusive] = $this->dateRange($request);

        $report = $reportService->profitAndLoss($fromDate, $toExclusive);
        $rows = [
            ['MIS Report', $from . ' to ' . $to],
            [],
            ['Sales', $report['sales']],
            ['Orders', $report['orders']],
            ['COGS', $report['cogs']],
            ['Gross profit', $report['gross_profit']],
            ['Expenses', $report['expenses']],
            ['Net profit', $report['net_profit']],
            [],
            ['Sales by day', '', ''],
            ['Date', 'Total'],
        ];

        foreach ($orders->salesByDay($fromDate, $toExclusive) as $row) {
            $rows[] = [$row['date'], $row['total']];
        }

        $rows[] = [];
        $rows[] = ['Sales by category', '', ''];
        $rows[] = ['Category', 'Total'];
        foreach ($orders->salesByCategory($fromDate, $toExclusive) as $row) {
            $rows[] = [$row['category'], $row['total']];
        }

        $rows[] = [];
        $rows[] = ['Expenses by category', '', ''];
        $rows[] = ['Category', 'Total'];
        foreach ($expenses->totalsByCategory($fromDate, $toExclusive) as $row) {
            $rows[] = [$row['category'], $row['total']];
        }

        $rows[] = [];
        $rows[] = ['Top products', '', '', ''];
        $rows[] = ['Product', 'Quantity sold', 'Revenue'];
        foreach ($orderItems->topProducts($fromDate, $toExclusive) as $row) {
            $rows[] = [$row['name'], $row['qty'], $row['revenue']];
        }

        $rows[] = [];
        $rows[] = ['Inventory valuation', '', '', '', ''];
        $rows[] = ['Product', 'SKU', 'Stock', 'Unit cost', 'Value'];
        $inventory = $reportService->inventoryValuation();
        foreach ($inventory as $row) {
            $rows[] = [$row['name'], $row['sku'], $row['stock'], $row['cost'], $row['value']];
        }
        $rows[] = ['TOTAL', '', '', '', array_sum(array_column($inventory, 'value'))];

        return $csv->export('mis-report-' . $from . '-to-' . $to . '.csv', [], $rows);
    }
}
