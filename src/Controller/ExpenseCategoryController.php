<?php

namespace App\Controller;

use App\Entity\ExpenseCategory;
use App\Repository\ExpenseCategoryRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/expenses/categories')]
class ExpenseCategoryController extends AbstractController
{
    #[Route('', name: 'expense_category_index', methods: ['GET'])]
    public function index(ExpenseCategoryRepository $categories): Response
    {
        return $this->render('expense/category/index.html.twig', [
            'categories' => $categories->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'expense_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            if ('' !== $name) {
                $category = (new ExpenseCategory())->setName($name);
                $em->persist($category);
                $em->flush();
                $audit->log('create', 'ExpenseCategory', $category->getId(), ['name' => $name]);
                $this->addFlash('success', 'Category created.');
            } else {
                $this->addFlash('danger', 'Name is required.');
            }

            return $this->redirectToRoute('expense_category_index');
        }

        return $this->render('expense/category/new.html.twig');
    }

    #[Route('/{id}/delete', name: 'expense_category_delete', methods: ['POST'])]
    public function delete(Request $request, ExpenseCategory $category, EntityManagerInterface $em, AuditService $audit): Response
    {
        if (!$this->isCsrfTokenValid('delete_expense_category_' . $category->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('expense_category_index');
        }

        if (count($category->getExpenses()) > 0) {
            $this->addFlash('danger', 'Cannot delete: category still has expenses.');

            return $this->redirectToRoute('expense_category_index');
        }

        $audit->log('delete', 'ExpenseCategory', $category->getId(), ['name' => $category->getName()]);
        $em->remove($category);
        $em->flush();
        $this->addFlash('success', 'Category deleted.');

        return $this->redirectToRoute('expense_category_index');
    }
}
