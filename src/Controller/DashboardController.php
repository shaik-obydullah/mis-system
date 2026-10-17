<?php

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Service\NotificationService;
use App\Service\ReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        ReportService $reportService,
        OrderRepository $orderRepository,
        EntityManagerInterface $em,
        NotificationService $notificationService,
    ): Response {
        $now = new \DateTimeImmutable();
        $monthFrom = $now->modify('first day of this month')->setTime(0, 0, 0);
        $monthTo = $monthFrom->modify('+1 month');

        $dashboard = $reportService->dashboard();
        $salesByDay = $orderRepository->salesByDay($now->modify('-13 days')->setTime(0, 0, 0), $monthTo);
        $salesByCategory = $orderRepository->salesByCategory($monthFrom, $monthTo);
        $lowStock = $em->getRepository(\App\Entity\Product::class)->findLowStock();
        $recentOrders = $orderRepository->findBy([], ['createdAt' => 'DESC'], 5);

        $notificationService->checkLowStock();

        return $this->render('dashboard/index.html.twig', [
            'dashboard' => $dashboard,
            'salesByDay' => $salesByDay,
            'salesByCategory' => $salesByCategory,
            'lowStock' => $lowStock,
            'recentOrders' => $recentOrders,
        ]);
    }
}
