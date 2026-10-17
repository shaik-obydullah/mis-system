<?php

namespace App\Controller\Inventory;

use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use App\Repository\ProductRepository;
use App\Repository\PurchaseRepository;
use App\Repository\SupplierRepository;
use App\Service\AuditService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventory/purchases')]
class PurchaseController extends AbstractController
{
    #[Route('', name: 'purchase_index', methods: ['GET'])]
    public function index(PurchaseRepository $purchases): Response
    {
        return $this->render('inventory/purchase/index.html.twig', [
            'purchases' => $purchases->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'purchase_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ProductRepository $products,
        SupplierRepository $suppliers,
        AuditService $audit,
        NotificationService $notifications,
    ): Response {
        if ($request->isMethod('POST')) {
            $productIds = $request->request->all('product_id');
            $quantities = $request->request->all('quantity');
            $costs = $request->request->all('unit_cost');

            $purchase = new Purchase();
            $purchase->setCreatedBy($this->getUser());
            $supplierId = (int) $request->request->get('supplier');
            $purchase->setSupplier($supplierId ? $suppliers->find($supplierId) : null);
            $purchase->setNote(trim((string) $request->request->get('note', '')) ?: null);
            $em->persist($purchase);

            $total = 0.0;
            foreach ($productIds as $index => $id) {
                $qty = (float) ($quantities[$index] ?? 0);
                $cost = (float) ($costs[$index] ?? 0);
                $product = $id ? $products->find($id) : null;

                if (!$product || $qty <= 0 || $cost <= 0) {
                    continue;
                }

                $lineTotal = round($qty * $cost, 2);

                $item = new PurchaseItem();
                $item->setProduct($product);
                $item->setQuantity((string) $qty);
                $item->setUnitCost((string) $cost);
                $item->setLineTotal((string) $lineTotal);

                $product->addStock((string) $qty);
                $purchase->addItem($item);
                $em->persist($item);

                $total += $lineTotal;
            }

            if (count($purchase->getItems()) === 0) {
                $em->remove($purchase);
                $this->addFlash('danger', 'Add at least one valid line item.');

                return $this->redirectToRoute('purchase_new');
            }

            $purchase->setTotal((string) round($total, 2));
            $em->flush();

            $audit->log('create', 'Purchase', $purchase->getId(), ['total' => $purchase->getTotal()]);
            $notifications->checkLowStock();
            $this->addFlash('success', 'Purchase recorded and stock updated.');

            return $this->redirectToRoute('purchase_index');
        }

        return $this->render('inventory/purchase/new.html.twig', [
            'products' => $products->findAll(),
            'suppliers' => $suppliers->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'purchase_show', methods: ['GET'])]
    public function show(Purchase $purchase): Response
    {
        return $this->render('inventory/purchase/show.html.twig', ['purchase' => $purchase]);
    }
}
