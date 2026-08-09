<?php

namespace App\Controller\Inventory;

use App\Entity\Product;
use App\Repository\ProductCategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SupplierRepository;
use App\Service\AuditService;
use App\Service\CsvService;
use App\Service\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventory/products')]
class ProductController extends AbstractController
{
    #[Route('', name: 'product_index', methods: ['GET'])]
    public function index(Request $request, ProductRepository $products, ProductCategoryRepository $categories, Paginator $paginator): Response
    {
        $qb = $products->createQueryBuilder('p')->orderBy('p.name', 'ASC');

        $q = trim((string) $request->query->get('q', ''));
        if ($q !== '') {
            $qb->andWhere('LOWER(p.name) LIKE :q OR LOWER(p.sku) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($q) . '%');
        }

        $category = (int) $request->query->get('category', 0);
        if ($category > 0) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category);
        }

        $pagination = $paginator->paginate($qb, (int) $request->query->get('page', 1), 25);

        return $this->render('inventory/product/index.html.twig', [
            'products' => $pagination['items'],
            'pagination' => $pagination,
            'q' => $q,
            'categoryFilter' => $category,
            'categories' => $categories->findAll(),
        ]);
    }

    #[Route('/export.csv', name: 'product_export', methods: ['GET'])]
    public function export(Request $request, ProductRepository $products, CsvService $csv): Response
    {
        $rows = [];
        foreach ($products->findBy([], ['name' => 'ASC']) as $product) {
            $rows[] = [
                $product->getName(),
                $product->getSku(),
                $product->getCategory()?->getName() ?? '',
                $product->getSupplier()?->getName() ?? '',
                $product->getPrice(),
                $product->getCost(),
                $product->getCurrentStock(),
                $product->getLowStockThreshold(),
                $product->isActive() ? 'active' : 'inactive',
            ];
        }

        return $csv->export('products.csv', ['Name', 'SKU', 'Category', 'Supplier', 'Price', 'Cost', 'Stock', 'Threshold', 'Status'], $rows);
    }


    #[Route('/new', name: 'product_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ProductCategoryRepository $categories,
        SupplierRepository $suppliers,
        AuditService $audit,
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                $product = new Product();
                $this->applyRequest($request, $product, $em);
                $em->persist($product);
                $em->flush();
                $audit->log('create', 'Product', $product->getId(), ['name' => $product->getName(), 'sku' => $product->getSku()]);
                $this->addFlash('success', 'Product created.');

                return $this->redirectToRoute('product_index');
            } catch (\Throwable $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('inventory/product/new.html.twig', [
            'categories' => $categories->findAll(),
            'suppliers' => $suppliers->findAll(),
        ]);
    }

    #[Route('/{id}/edit', name: 'product_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Product $product,
        EntityManagerInterface $em,
        ProductCategoryRepository $categories,
        SupplierRepository $suppliers,
        AuditService $audit,
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                $this->applyRequest($request, $product, $em);
                $em->flush();
                $audit->log('update', 'Product', $product->getId(), ['name' => $product->getName()]);
                $this->addFlash('success', 'Product updated.');

                return $this->redirectToRoute('product_index');
            } catch (\Throwable $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('inventory/product/edit.html.twig', [
            'product' => $product,
            'categories' => $categories->findAll(),
            'suppliers' => $suppliers->findAll(),
        ]);
    }

    #[Route('/{id}/delete', name: 'product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $em, AuditService $audit): Response
    {
        if (!$this->isCsrfTokenValid('delete_product_' . $product->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('product_index');
        }

        $audit->log('delete', 'Product', $product->getId(), ['name' => $product->getName()]);
        $em->remove($product);
        $em->flush();
        $this->addFlash('success', 'Product deleted.');

        return $this->redirectToRoute('product_index');
    }

    private function applyRequest(Request $request, Product $product, EntityManagerInterface $em): void
    {
        $name = trim((string) $request->request->get('name', ''));
        $sku = trim((string) $request->request->get('sku', ''));

        if ('' === $name || '' === $sku) {
            throw new \InvalidArgumentException('Name and SKU are required.');
        }

        $product->setName($name);
        $product->setSku($sku);
        $product->setPrice((string) ((float) $request->request->get('price', 0)));
        $product->setCost((string) ((float) $request->request->get('cost', 0)));
        $product->setCurrentStock((string) ((float) $request->request->get('current_stock', 0)));
        $product->setLowStockThreshold((string) ((float) $request->request->get('low_stock_threshold', 5)));
        $product->setActive((bool) $request->request->get('active', false));

        $categoryId = $request->request->get('category');
        $product->setCategory($categoryId ? $em->getRepository(\App\Entity\ProductCategory::class)->find((int) $categoryId) : null);

        $supplierId = $request->request->get('supplier');
        $product->setSupplier($supplierId ? $em->getRepository(\App\Entity\Supplier::class)->find((int) $supplierId) : null);
    }
}
