<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use App\Service\AuditService;
use App\Service\OrderService;
use App\Service\ReportService;
use App\Service\SettingsService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ApiController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwtManager,
    ): JsonResponse {
        $data = json_decode((string) $request->getContent(), true) ?? $request->request->all();
        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $user = $users->findOneByEmail($email);

        if (!$user || !$user->isActive() || !$hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json(['token' => $jwtManager->create($user)]);
    }

    #[Route('/orders', name: 'api_orders_list', methods: ['GET'])]
    public function orders(Request $request, OrderRepository $orders): JsonResponse
    {
        $qb = $orders->createQueryBuilder('o')->orderBy('o.createdAt', 'DESC')->setMaxResults(100);

        if ($from = $request->query->get('from')) {
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', new \DateTimeImmutable($from . ' 00:00:00'));
        }
        if ($to = $request->query->get('to')) {
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', new \DateTimeImmutable($to . ' 23:59:59'));
        }
        if ($status = $request->query->get('status')) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        $result = array_map(fn (Order $o) => $this->serializeOrder($o), $qb->getQuery()->getResult());

        return $this->json(['data' => $result]);
    }

    #[Route('/orders/{id}', name: 'api_orders_show', methods: ['GET'])]
    public function orderShow(Order $order): JsonResponse
    {
        return $this->json(['data' => $this->serializeOrder($order, true)]);
    }

    #[Route('/orders', name: 'api_orders_create', methods: ['POST'])]
    public function orderCreate(
        Request $request,
        OrderService $orderService,
        ProductRepository $products,
        SettingsService $settings,
        AuditService $audit,
    ): JsonResponse {
        $data = json_decode((string) $request->getContent(), true) ?? $request->request->all();

        if (empty($data['items']) || !is_array($data['items'])) {
            return $this->json(['error' => 'items[] is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $items = [];
        foreach ($data['items'] as $row) {
            $product = $products->find((int) ($row['product_id'] ?? 0));
            if (!$product) {
                return $this->json(['error' => sprintf('Product %d not found.', (int) ($row['product_id'] ?? 0))], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $items[] = ['product' => $product, 'quantity' => (float) ($row['quantity'] ?? 0)];
        }

        try {
            $taxRate = (float) $settings->get('tax_rate', '0') / 100;
            $order = $orderService->createOrder(
                $this->getUser(),
                $items,
                isset($data['customer']) ? trim((string) $data['customer']) : null,
                $taxRate,
            );

            $method = (string) ($data['payment_method'] ?? '');
            if (in_array($method, [Order::METHOD_CASH, Order::METHOD_CARD, Order::METHOD_ONLINE], true)) {
                $orderService->markPaid($order, $method);
            }

            $audit->log('api.create', 'Order', $order->getId(), ['no' => $order->getOrderNo()]);

            return $this->json(['data' => $this->serializeOrder($order, true)], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/products', name: 'api_products', methods: ['GET'])]
    public function products(ProductRepository $products): JsonResponse
    {
        $result = array_map(static function ($p) {
            return [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'sku' => $p->getSku(),
                'category' => $p->getCategory()?->getName(),
                'price' => $p->getPrice(),
                'cost' => $p->getCost(),
                'current_stock' => $p->getCurrentStock(),
                'low_stock_threshold' => $p->getLowStockThreshold(),
                'active' => $p->isActive(),
            ];
        }, $products->findAll());

        return $this->json(['data' => $result]);
    }

    #[Route('/reports/sales', name: 'api_reports_sales', methods: ['GET'])]
    public function salesReport(Request $request, ReportService $reportService): JsonResponse
    {
        $from = new \DateTimeImmutable((string) $request->query->get('from', date('Y-m-01')) . ' 00:00:00');
        $to = new \DateTimeImmutable((string) $request->query->get('to', date('Y-m-d')) . ' 23:59:59');
        $toExclusive = $to->modify('+1 second');

        return $this->json(['data' => $reportService->profitAndLoss($from, $toExclusive)]);
    }

    private function serializeOrder(Order $order, bool $withItems = false): array
    {
        $data = [
            'id' => $order->getId(),
            'order_no' => $order->getOrderNo(),
            'customer' => $order->getCustomer(),
            'status' => $order->getStatus(),
            'payment_method' => $order->getPaymentMethod(),
            'subtotal' => $order->getSubtotal(),
            'tax' => $order->getTax(),
            'total' => $order->getTotal(),
            'created_at' => $order->getCreatedAt()->format('c'),
            'paid_at' => $order->getPaidAt()?->format('c'),
            'created_by' => $order->getCreatedBy()?->getEmail(),
        ];

        if ($withItems) {
            $data['items'] = array_map(static function ($item) {
                return [
                    'product_id' => $item->getProduct()->getId(),
                    'product' => $item->getProduct()->getName(),
                    'quantity' => $item->getQuantity(),
                    'unit_price' => $item->getUnitPrice(),
                    'line_total' => $item->getLineTotal(),
                ];
            }, $order->getItems()->toArray());
        }

        return $data;
    }
}
