<?php

namespace App\Controller;

use App\Repository\ExpenseRepository;
use App\Repository\OrderItemRepository;
use App\Repository\OrderRepository;
use App\Service\ReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reports')]
class ReportController extends AbstractController
{
    #[Route('', name: 'report_index', methods: ['GET'])]
    public function index(
        Request $request,
        ReportService $reportService,
        OrderRepository $orders,
        ExpenseRepository $expenses,
        OrderItemRepository $orderItems,
    ): Response {
        $from = (string) $request->query->get('from', (new \DateTimeImmutable('first day of this month'))->format('Y-m-d'));
        $to = (string) $request->query->get('to', (new \DateTimeImmutable())->format('Y-m-d'));

        $fromDate = new \DateTimeImmutable($from . ' 00:00:00');
        $toDate = new \DateTimeImmutable($to . ' 23:59:59');
        $toExclusive = $toDate->modify('+1 second');

        $report = $reportService->profitAndLoss($fromDate, $toExclusive);

        return $this->render('report/index.html.twig', [
            'from' => $from,
            'to' => $to,
            'report' => $report,
            'salesByDay' => $orders->salesByDay($fromDate, $toExclusive),
            'salesByCategory' => $orders->salesByCategory($fromDate, $toExclusive),
            'expenseBreakdown' => $expenses->totalsByCategory($fromDate, $toExclusive),
            'topProducts' => $orderItems->topProducts($fromDate, $toExclusive),
        ]);
    }
}
