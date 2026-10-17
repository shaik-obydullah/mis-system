<?php

namespace App\Controller\Staff;

use App\Entity\SalaryRecord;
use App\Repository\EmployeeRepository;
use App\Repository\SalaryRecordRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/staff/salaries')]
class SalaryController extends AbstractController
{
    #[Route('', name: 'salary_index', methods: ['GET'])]
    public function index(SalaryRecordRepository $salaries): Response
    {
        return $this->render('staff/salary/index.html.twig', [
            'salaries' => $salaries->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'salary_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, EmployeeRepository $employees, AuditService $audit): Response
    {
        if ($request->isMethod('POST')) {
            $employeeId = (int) $request->request->get('employee');
            $period = (string) $request->request->get('period', date('Y-m'));
            $amount = (float) $request->request->get('amount', 0);

            $employee = $employees->find($employeeId);
            if (!$employee || $amount <= 0 || !preg_match('/^\d{4}-\d{2}$/', $period)) {
                $this->addFlash('danger', 'Employee, a valid period (YYYY-MM) and a positive amount are required.');

                return $this->render('staff/salary/new.html.twig', ['employees' => $employees->findActive()]);
            }

            $existing = $em->getRepository(SalaryRecord::class)->findOneBy(['employee' => $employee, 'period' => $period]);
            if ($existing) {
                $this->addFlash('danger', sprintf('%s already has a salary record for %s.', $employee->getName(), $period));

                return $this->render('staff/salary/new.html.twig', ['employees' => $employees->findActive()]);
            }

            $record = new SalaryRecord();
            $record->setEmployee($employee);
            $record->setPeriod($period);
            $record->setAmount((string) $amount);
            $record->setPaidAt(new \DateTimeImmutable());
            $record->setCreatedBy($this->getUser());
            $em->persist($record);
            $em->flush();

            $audit->log('create', 'SalaryRecord', $record->getId(), ['employee' => $employee->getName(), 'period' => $period, 'amount' => $amount]);
            $this->addFlash('success', 'Salary payment recorded.');

            return $this->redirectToRoute('salary_index');
        }

        return $this->render('staff/salary/new.html.twig', ['employees' => $employees->findActive()]);
    }
}
