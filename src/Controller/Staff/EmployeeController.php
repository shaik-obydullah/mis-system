<?php

namespace App\Controller\Staff;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/staff/employees')]
class EmployeeController extends AbstractController
{
    #[Route('', name: 'employee_index', methods: ['GET'])]
    public function index(EmployeeRepository $employees): Response
    {
        return $this->render('staff/employee/index.html.twig', [
            'employees' => $employees->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'employee_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            if ('' === $name) {
                $this->addFlash('danger', 'Name is required.');

                return $this->render('staff/employee/new.html.twig');
            }

            $employee = new Employee();
            $this->applyRequest($request, $employee);
            $em->persist($employee);
            $em->flush();
            $audit->log('create', 'Employee', $employee->getId(), ['name' => $employee->getName()]);
            $this->addFlash('success', 'Employee added.');

            return $this->redirectToRoute('employee_index');
        }

        return $this->render('staff/employee/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'employee_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Employee $employee, EntityManagerInterface $em, AuditService $audit): Response
    {
        if ($request->isMethod('POST')) {
            $this->applyRequest($request, $employee);
            $em->flush();
            $audit->log('update', 'Employee', $employee->getId(), ['name' => $employee->getName()]);
            $this->addFlash('success', 'Employee updated.');

            return $this->redirectToRoute('employee_index');
        }

        return $this->render('staff/employee/edit.html.twig', ['employee' => $employee]);
    }

    #[Route('/{id}/delete', name: 'employee_delete', methods: ['POST'])]
    public function delete(Request $request, Employee $employee, EntityManagerInterface $em, AuditService $audit): Response
    {
        if (!$this->isCsrfTokenValid('delete_employee_' . $employee->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('employee_index');
        }

        $audit->log('delete', 'Employee', $employee->getId(), ['name' => $employee->getName()]);
        $em->remove($employee);
        $em->flush();
        $this->addFlash('success', 'Employee removed.');

        return $this->redirectToRoute('employee_index');
    }

    private function applyRequest(Request $request, Employee $employee): void
    {
        $employee->setName(trim((string) $request->request->get('name', '')));
        $employee->setRole((string) $request->request->get('role', 'staff'));
        $employee->setPhone(trim((string) $request->request->get('phone', '')) ?: null);
        $employee->setBaseSalary((string) ((float) $request->request->get('base_salary', 0)));
        $hireDate = (string) $request->request->get('hire_date', date('Y-m-d'));
        $employee->setHireDate(new \DateTimeImmutable($hireDate . ' 12:00:00'));
        $employee->setActive((bool) $request->request->get('active', false));
    }
}
