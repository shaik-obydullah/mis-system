<?php

namespace App\Controller\Staff;

use App\Entity\Attendance;
use App\Repository\AttendanceRepository;
use App\Repository\EmployeeRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/staff/attendance')]
class AttendanceController extends AbstractController
{
    #[Route('', name: 'attendance_index', methods: ['GET'])]
    public function index(Request $request, AttendanceRepository $attendances, EmployeeRepository $employees): Response
    {
        $date = (string) $request->query->get('date', date('Y-m-d'));
        $day = new \DateTime($date . ' 00:00:00');

        $rows = $attendances->createQueryBuilder('a')
            ->where('a.date = :date')
            ->setParameter('date', $day)
            ->orderBy('a.employee', 'ASC')
            ->getQuery()
            ->getResult();

        $existing = [];
        foreach ($rows as $row) {
            $existing[$row->getEmployee()->getId()] = $row;
        }

        return $this->render('staff/attendance/index.html.twig', [
            'date' => $date,
            'employees' => $employees->findActive(),
            'existing' => $existing,
            'summary' => $attendances->summaryForMonth($date),
        ]);
    }

    #[Route('/save', name: 'attendance_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em, EmployeeRepository $employees, AuditService $audit): Response
    {
        $date = (string) $request->request->get('date', date('Y-m-d'));
        $day = new \DateTime($date . ' 00:00:00');
        $statuses = $request->request->all('status');
        $notes = $request->request->all('note');

        foreach ($statuses as $employeeId => $status) {
            $employee = $employees->find((int) $employeeId);
            if (!$employee) {
                continue;
            }

            $record = $em->getRepository(Attendance::class)->findOneByEmployeeAndDate($employee->getId(), $day);
            if (!$record) {
                $record = new Attendance();
                $record->setEmployee($employee);
                $record->setDate($day);
                $em->persist($record);
            }

            $record->setStatus($status);
            $note = trim((string) ($notes[$employeeId] ?? ''));
            $record->setNote($note ?: null);
        }

        $em->flush();
        $audit->log('update', 'Attendance', null, ['date' => $date]);
        $this->addFlash('success', 'Attendance saved for ' . $date . '.');

        return $this->redirectToRoute('attendance_index', ['date' => $date]);
    }
}
