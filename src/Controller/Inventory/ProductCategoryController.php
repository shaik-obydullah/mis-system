<?php

namespace App\Controller\Inventory;

use App\Entity\ProductCategory;
use App\Repository\ProductCategoryRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/inventory/categories')]
class ProductCategoryController extends AbstractController
{
    #[Route('', name: 'product_category_index', methods: ['GET'])]
    public function index(ProductCategoryRepository $categories): Response
    {
        return $this->render('inventory/category/index.html.twig', [
            'categories' => $categories->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'product_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            if ('' !== $name) {
                $category = (new ProductCategory())->setName($name);
                $em->persist($category);
                $em->flush();
                $audit->log('create', 'ProductCategory', $category->getId(), ['name' => $name]);
                $this->addFlash('success', 'Category created.');
            } else {
                $this->addFlash('danger', 'Name is required.');
            }

            return $this->redirectToRoute('product_category_index');
        }

        return $this->render('inventory/category/new.html.twig');
    }

    #[Route('/{id}/delete', name: 'product_category_delete', methods: ['POST'])]
    public function delete(Request $request, ProductCategory $category, EntityManagerInterface $em, AuditService $audit): Response
    {
        if (!$this->isCsrfTokenValid('delete_category_' . $category->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('product_category_index');
        }

        if (count($category->getProducts()) > 0) {
            $this->addFlash('danger', 'Cannot delete: category still has products.');

            return $this->redirectToRoute('product_category_index');
        }

        $audit->log('delete', 'ProductCategory', $category->getId(), ['name' => $category->getName()]);
        $em->remove($category);
        $em->flush();
        $this->addFlash('success', 'Category deleted.');

        return $this->redirectToRoute('product_category_index');
    }
}
