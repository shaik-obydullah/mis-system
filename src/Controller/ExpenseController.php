<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Repository\ExpenseCategoryRepository;
use App\Repository\ExpenseRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/expenses')]
class ExpenseController extends AbstractController
{
    #[Route('', name: 'expense_index', methods: ['GET'])]
    public function index(Request $request, ExpenseRepository $expenses): Response
    {
        $qb = $expenses->createQueryBuilder('e')->orderBy('e.date', 'DESC');

        $month = $request->query->get('month');
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $from = new \DateTimeImmutable($month . '-01');
            $to = $from->modify('+1 month');
            $qb->andWhere('e.date >= :from')->andWhere('e.date < :to')
                ->setParameter('from', $from)->setParameter('to', $to);
        } else {
            $month = (new \DateTimeImmutable())->format('Y-m');
            $from = new \DateTimeImmutable($month . '-01');
            $to = $from->modify('+1 month');
            $qb->andWhere('e.date >= :from')->andWhere('e.date < :to')
                ->setParameter('from', $from)->setParameter('to', $to);
        }

        $expenseList = $qb->getQuery()->getResult();
        $total = 0.0;
        foreach ($expenseList as $e) {
            $total += (float) $e->getAmount();
        }

        return $this->render('expense/index.html.twig', [
            'expenses' => $expenseList,
            'total' => (string) round($total, 2),
            'month' => $month,
        ]);
    }

    #[Route('/new', name: 'expense_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ExpenseCategoryRepository $categories, AuditService $audit): Response
    {
        if ($request->isMethod('POST')) {
            $amount = (float) $request->request->get('amount', 0);
            $categoryId = (int) $request->request->get('category');
            $date = (string) $request->request->get('date', date('Y-m-d'));

            if ($amount <= 0 || !$categoryId) {
                $this->addFlash('danger', 'Amount and category are required.');

                return $this->render('expense/new.html.twig', ['categories' => $categories->findAll()]);
            }

            $expense = new Expense();
            $expense->setAmount((string) $amount);
            $expense->setCategory($categories->find($categoryId));
            $expense->setDescription(trim((string) $request->request->get('description', '')) ?: null);
            $expense->setDate(new \DateTimeImmutable($date . ' 12:00:00'));
            $expense->setCreatedBy($this->getUser());

            $em->persist($expense);
            $em->flush();

            $audit->log('create', 'Expense', $expense->getId(), ['amount' => $amount, 'category' => $expense->getCategory()?->getName()]);
            $this->addFlash('success', 'Expense recorded.');

            return $this->redirectToRoute('expense_index');
        }

        return $this->render('expense/new.html.twig', ['categories' => $categories->findAll()]);
    }

    #[Route('/{id}/delete', name: 'expense_delete', methods: ['POST'])]
    public function delete(Request $request, Expense $expense, EntityManagerInterface $em, AuditService $audit): Response
    {
        if (!$this->isCsrfTokenValid('delete_expense_' . $expense->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('expense_index');
        }

        $audit->log('delete', 'Expense', $expense->getId(), ['amount' => $expense->getAmount()]);
        $em->remove($expense);
        $em->flush();
        $this->addFlash('success', 'Expense deleted.');

        return $this->redirectToRoute('expense_index');
    }
}
