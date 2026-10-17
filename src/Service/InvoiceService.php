<?php

namespace App\Service;

use App\Entity\Expense;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class InvoiceService
{
    public function __construct(
        private Environment $twig,
        private SettingsService $settings,
    ) {
    }

    public function render(Order $order): string
    {
        $business = [
            'name' => $this->settings->get('business_name', 'MIS System'),
            'currency' => $this->settings->get('currency', 'USD'),
        ];

        $html = $this->twig->render('invoice/invoice.html.twig', [
            'order' => $order,
            'business' => $business,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
