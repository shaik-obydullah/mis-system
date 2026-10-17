<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'notification_index', methods: ['GET'])]
    public function index(NotificationRepository $notifications): Response
    {
        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications->findRecent(100),
        ]);
    }

    #[Route('/read-all', name: 'notification_read_all', methods: ['POST'])]
    public function readAll(EntityManagerInterface $em, NotificationRepository $notifications): Response
    {
        foreach ($notifications->findRecent(500) as $notification) {
            $notification->setIsRead(true);
        }
        $em->flush();
        $this->addFlash('success', 'All notifications marked as read.');

        return $this->redirectToRoute('notification_index');
    }
}
