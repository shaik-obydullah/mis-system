<?php

namespace App\Controller\Inventory;

use App\Entity\Supplier;
use App\Repository\SupplierRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventory/suppliers')]
class SupplierController extends AbstractController
{
    #[Route('', name: 'supplier_index', methods: ['GET'])]
    public function index(SupplierRepository $suppliers): Response
    {
        return $this->render('inventory/supplier/index.html.twig', [
            'suppliers' => $suppliers->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'supplier_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            if ('' === $name) {
                $this->addFlash('danger', 'Name is required.');

                return $this->render('inventory/supplier/new.html.twig');
            }

            $supplier = new Supplier();
            $this->applyRequest($request, $supplier);
            $em->persist($supplier);
            $em->flush();
            $audit->log('create', 'Supplier', $supplier->getId(), ['name' => $supplier->getName()]);
            $this->addFlash('success', 'Supplier created.');

            return $this->redirectToRoute('supplier_index');
        }

        return $this->render('inventory/supplier/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'supplier_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Supplier $supplier, EntityManagerInterface $em, AuditService $audit): Response
    {
        if ($request->isMethod('POST')) {
            $this->applyRequest($request, $supplier);
            $em->flush();
            $audit->log('update', 'Supplier', $supplier->getId(), ['name' => $supplier->getName()]);
            $this->addFlash('success', 'Supplier updated.');

            return $this->redirectToRoute('supplier_index');
        }

        return $this->render('inventory/supplier/edit.html.twig', ['supplier' => $supplier]);
    }

    #[Route('/{id}/delete', name: 'supplier_delete', methods: ['POST'])]
    public function delete(Request $request, Supplier $supplier, EntityManagerInterface $em, AuditService $audit): Response
    {
        if (!$this->isCsrfTokenValid('delete_supplier_' . $supplier->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('supplier_index');
        }

        if (count($supplier->getProducts()) > 0 || count($supplier->getPurchases()) > 0) {
            $this->addFlash('danger', 'Cannot delete: supplier has products or purchases.');

            return $this->redirectToRoute('supplier_index');
        }

        $audit->log('delete', 'Supplier', $supplier->getId(), ['name' => $supplier->getName()]);
        $em->remove($supplier);
        $em->flush();
        $this->addFlash('success', 'Supplier deleted.');

        return $this->redirectToRoute('supplier_index');
    }

    private function applyRequest(Request $request, Supplier $supplier): void
    {
        $supplier->setName(trim((string) $request->request->get('name', '')));
        $supplier->setContactPerson(trim((string) $request->request->get('contact_person', '')) ?: null);
        $supplier->setPhone(trim((string) $request->request->get('phone', '')) ?: null);
        $supplier->setEmail(trim((string) $request->request->get('email', '')) ?: null);
        $supplier->setAddress(trim((string) $request->request->get('address', '')) ?: null);
    }
}
