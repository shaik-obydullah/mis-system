<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\AuditService;
use App\Service\CsvService;
use App\Service\InvoiceService;
use App\Service\OrderService;
use App\Service\Paginator;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/orders')]
class OrderController extends AbstractController
{
    private function filterQuery(Request $request, OrderRepository $orders): \Doctrine\ORM\QueryBuilder
    {
        $qb = $orders->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC');

        $q = trim((string) $request->query->get('q', ''));
        if ($q !== '') {
            $qb->andWhere('LOWER(o.orderNo) LIKE :q OR LOWER(COALESCE(o.customer, \'\')) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $status = $request->query->get('status');

        if ($from) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', new \DateTimeImmutable($from . ' 00:00:00'));
        }
        if ($to) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', new \DateTimeImmutable($to . ' 23:59:59'));
        }
        if ($status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        return $qb;
    }

    private function filters(Request $request): array
    {
        return [
            'q' => (string) $request->query->get('q', ''),
            'from' => $request->query->get('from'),
            'to' => $request->query->get('to'),
            'status' => $request->query->get('status'),
        ];
    }

    #[Route('', name: 'order_index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orders, Paginator $paginator): Response
    {
        $this->denyAccessUnlessGranted('order.view');

        $qb = $this->filterQuery($request, $orders);
        $pagination = $paginator->paginate($qb, (int) $request->query->get('page', 1), 25);

        return $this->render('order/index.html.twig', [
            'orders' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $this->filters($request),
        ]);
    }

    #[Route('/export.csv', name: 'order_export', methods: ['GET'])]
    public function export(Request $request, OrderRepository $orders, CsvService $csv): Response
    {
        $this->denyAccessUnlessGranted('order.export');

        $qb = $this->filterQuery($request, $orders);

        $rows = [];
        foreach ($qb->getQuery()->getResult() as $order) {
            $rows[] = [
                $order->getOrderNo(),
                $order->getCreatedAt()->format('Y-m-d H:i'),
                $order->getCustomer() ?? '',
                $order->getStatus(),
                $order->getPaymentMethod() ?? '',
                $order->getSubtotal(),
                $order->getTax(),
                $order->getTotal(),
            ];
        }

        return $csv->export('orders.csv', ['Order no', 'Created', 'Customer', 'Status', 'Payment', 'Subtotal', 'Tax', 'Total'], $rows);
    }

    #[Route('/new', name: 'order_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        ProductRepository $products,
        OrderService $orderService,
        SettingsService $settings,
        AuditService $audit,
    ): Response {
        $this->denyAccessUnlessGranted('order.create');

        if ($request->isMethod('POST')) {
            $productIds = $request->request->all('product_id');
            $quantities = $request->request->all('quantity');

            $items = [];
            foreach ($productIds as $index => $id) {
                $qty = (float) ($quantities[$index] ?? 0);
                if (!$id || $qty <= 0) {
                    continue;
                }

                $product = $products->find($id);
                if (!$product || !$product->isActive()) {
                    continue;
                }

                $items[] = ['product' => $product, 'quantity' => $qty];
            }

            try {
                $customer = trim((string) $request->request->get('customer', ''));
                $taxRate = (float) $settings->get('tax_rate', '0') / 100;
                $order = $orderService->createOrder($this->getUser(), $items, $customer, $taxRate);

                $paymentMethod = (string) $request->request->get('payment_method', '');
                if (in_array($paymentMethod, [Order::METHOD_CASH, Order::METHOD_CARD, Order::METHOD_ONLINE], true)) {
                    $orderService->markPaid($order, $paymentMethod);
                }

                $audit->log('create', 'Order', $order->getId(), ['no' => $order->getOrderNo(), 'total' => $order->getTotal()]);
                $this->addFlash('success', sprintf('Order %s created.', $order->getOrderNo()));

                return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('order/new.html.twig', [
            'products' => $products->findActive(),
        ]);
    }

    #[Route('/{id}', name: 'order_show', methods: ['GET'])]
    public function show(Order $order): Response
    {
        $this->denyAccessUnlessGranted('order.view');

        return $this->render('order/show.html.twig', ['order' => $order]);
    }

    #[Route('/{id}/pay', name: 'order_pay', methods: ['POST'])]
    public function pay(Request $request, Order $order, OrderService $orderService, AuditService $audit): Response
    {
        $this->denyAccessUnlessGranted('order.pay');

        if (!$this->isCsrfTokenValid('order_pay_' . $order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        $method = (string) $request->request->get('payment_method', Order::METHOD_CASH);

        if (!in_array($method, [Order::METHOD_CASH, Order::METHOD_CARD, Order::METHOD_ONLINE], true)) {
            $method = Order::METHOD_CASH;
        }

        try {
            $orderService->markPaid($order, $method);
            $audit->log('pay', 'Order', $order->getId(), ['method' => $method, 'total' => $order->getTotal()]);
            $this->addFlash('success', 'Payment recorded.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/cancel', name: 'order_cancel', methods: ['POST'])]
    public function cancel(Request $request, Order $order, OrderService $orderService, AuditService $audit): Response
    {
        $this->denyAccessUnlessGranted('order.cancel');

        if (!$this->isCsrfTokenValid('order_cancel_' . $order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        $orderService->cancel($order);
        $audit->log('cancel', 'Order', $order->getId(), ['no' => $order->getOrderNo()]);
        $this->addFlash('success', 'Order cancelled and stock restored.');

        return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/refund', name: 'order_refund', methods: ['POST'])]
    public function refund(Request $request, Order $order, OrderService $orderService, AuditService $audit): Response
    {
        $this->denyAccessUnlessGranted('order.refund');

        if (!$this->isCsrfTokenValid('order_refund_' . $order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
        }

        try {
            $orderService->refund($order);
            $audit->log('refund', 'Order', $order->getId(), ['no' => $order->getOrderNo()]);
            $this->addFlash('success', 'Order refunded and stock restored.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id}/invoice', name: 'order_invoice', methods: ['GET'])]
    public function invoice(Order $order, InvoiceService $invoiceService): Response
    {
        $pdf = $invoiceService->render($order);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-' . $order->getOrderNo() . '.pdf"',
        ]);
    }
}
