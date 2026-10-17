<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function notify(string $message, string $type = Notification::TYPE_SYSTEM): Notification
    {
        $notification = new Notification();
        $notification->setMessage($message);
        $notification->setType($type);
        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    public function unreadCount(): int
    {
        return $this->em->getRepository(Notification::class)->countUnread();
    }

    /**
     * @return Notification[]
     */
    public function recent(int $limit = 10): array
    {
        return $this->em->getRepository(Notification::class)->findRecent($limit);
    }

    public function checkLowStock(): int
    {
        $products = $this->em->getRepository(Product::class)->findLowStock();
        $created = 0;

        foreach ($products as $product) {
            $message = sprintf('Low stock alert: %s — only %s left', $product->getName(), rtrim(rtrim($product->getCurrentStock(), '0'), '.'));

            if ($this->em->getRepository(Notification::class)->hasSimilarSince($message)) {
                continue;
            }

            $this->notify($message, Notification::TYPE_STOCK);
            ++$created;
        }

        return $created;
    }
}
