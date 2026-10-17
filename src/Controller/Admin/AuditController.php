<?php

namespace App\Controller\Admin;

use App\Repository\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/audit')]
class AuditController extends AbstractController
{
    #[Route('', name: 'admin_audit', methods: ['GET'])]
    public function index(AuditLogRepository $logs): Response
    {
        $this->denyAccessUnlessGranted('audit.view');

        return $this->render('admin/audit/index.html.twig', [
            'logs' => $logs->findBy([], ['createdAt' => 'DESC'], 200),
        ]);
    }
}
