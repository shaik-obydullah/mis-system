<?php

namespace App\Controller\Admin;

use App\Service\AuditService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/settings')]
class SettingController extends AbstractController
{
    #[Route('', name: 'admin_settings', methods: ['GET', 'POST'])]
    public function index(Request $request, SettingsService $settings, AuditService $audit): Response
    {
        $this->denyAccessUnlessGranted('setting.manage');

        if ($request->isMethod('POST')) {
            $settings->set('business_name', trim((string) $request->request->get('business_name', '')));
            $settings->set('currency', trim((string) $request->request->get('currency', 'USD')));
            $settings->set('timezone', trim((string) $request->request->get('timezone', 'UTC')));
            $settings->set('tax_rate', trim((string) $request->request->get('tax_rate', '0')));
            $settings->set('daily_summary_email', trim((string) $request->request->get('daily_summary_email', '')));
            $settings->set('date_format', trim((string) $request->request->get('date_format', 'Y-m-d')));
            $settings->set('time_format', trim((string) $request->request->get('time_format', 'H:i')));

            $audit->log('update', 'Setting', null, ['keys' => array_keys($request->request->all())]);
            $this->addFlash('success', 'Settings saved.');

            return $this->redirectToRoute('admin_settings');
        }

        return $this->render('admin/settings/index.html.twig', [
            'values' => [
                'business_name' => $settings->get('business_name', 'MIS System'),
                'currency' => $settings->get('currency', 'USD'),
                'timezone' => $settings->get('timezone', 'UTC'),
                'tax_rate' => $settings->get('tax_rate', '0'),
                'daily_summary_email' => $settings->get('daily_summary_email', ''),
                'date_format' => $settings->get('date_format', 'Y-m-d'),
                'time_format' => $settings->get('time_format', 'H:i'),
            ],
        ]);
    }
}
