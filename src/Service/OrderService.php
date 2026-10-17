<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private EntityManagerInterface $em,
        private AuditService $audit,
    ) {
    }

    /**
     * @param array<int, array{product: Product, quantity: float}> $items
     */
    public function createOrder(User $user, array $items, ?string $customer = null, float $taxRate = 0.0, ?\DateTimeImmutable $createdAt = null): Order
    {
        if (empty($items)) {
            throw new \InvalidArgumentException('Order must contain at least one item.');
        }

        $order = new Order();
        $order->setOrderNo($this->em->getRepository(Order::class)->findNextOrderNo());
        $order->setCustomer($customer ?: null);
        $order->setCreatedBy($user);

        foreach ($items as $row) {
            $quantity = (float) $row['quantity'];

            if ($quantity <= 0) {
                throw new \InvalidArgumentException(sprintf('Invalid quantity for product "%s".', $row['product']->getName()));
            }

            if ((float) $row['product']->getCurrentStock() < $quantity) {
                throw new \InvalidArgumentException(sprintf('Not enough stock for "%s" (available: %s).', $row['product']->getName(), $row['product']->getCurrentStock()));
            }
        }

        $this->em->persist($order);

        if ($createdAt) {
            $order->setCreatedAtForFixture($createdAt);
        }

        $subtotal = 0.0;

        foreach ($items as $row) {
            $product = $row['product'];
            $quantity = (float) $row['quantity'];

            $lineTotal = round((float) $product->getPrice() * $quantity, 2);

            $item = new OrderItem();
            $item->setProduct($product);
            $item->setQuantity((string) $quantity);
            $item->setUnitPrice($product->getPrice());
            $item->setLineTotal((string) $lineTotal);

            $product->reduceStock((string) $quantity);
            $order->addItem($item);

            $subtotal += $lineTotal;
        }

        $tax = round($subtotal * $taxRate, 2);
        $order->setSubtotal((string) round($subtotal, 2));
        $order->setTax((string) $tax);
        $order->setTotal((string) round($subtotal + $tax, 2));

        $this->em->flush();

        return $order;
    }

    public function markPaid(Order $order, string $method): void
    {
        $order->setStatus(Order::STATUS_PAID);
        $order->setPaymentMethod($method);
        $order->setPaidAt(new \DateTimeImmutable());
        $this->em->flush();
    }

    public function cancel(Order $order): void
    {
        if (in_array($order->getStatus(), [Order::STATUS_PAID, Order::STATUS_OPEN], true)) {
            foreach ($order->getItems() as $item) {
                $item->getProduct()->addStock($item->getQuantity());
            }
        }

        $order->setStatus(Order::STATUS_CANCELLED);
        $this->em->flush();
    }

    public function refund(Order $order): void
    {
        if (Order::STATUS_PAID !== $order->getStatus()) {
            throw new \LogicException('Only paid orders can be refunded.');
        }

        foreach ($order->getItems() as $item) {
            $item->getProduct()->addStock($item->getQuantity());
        }

        $order->setStatus(Order::STATUS_REFUNDED);
        $this->em->flush();
    }
}
