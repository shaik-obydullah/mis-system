<?php

namespace App\Controller\Admin;

use App\Entity\Permission;
use App\Repository\PermissionRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/permissions')]
class PermissionController extends AbstractController
{
    #[Route('', name: 'admin_permissions', methods: ['GET'])]
    public function index(PermissionRepository $permissions): Response
    {
        $this->denyAccessUnlessGranted('permission.manage');

        return $this->render('admin/permission/index.html.twig', [
            'permissions' => $permissions->findAllGrouped(),
        ]);
    }

    #[Route('/new', name: 'admin_permission_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        $this->denyAccessUnlessGranted('permission.manage');

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $label = trim((string) $request->request->get('label', ''));

            if ('' === $name || '' === $label) {
                $this->addFlash('danger', 'Name and label are required.');

                return $this->render('admin/permission/new.html.twig');
            }

            if ($em->getRepository(Permission::class)->findByName($name)) {
                $this->addFlash('danger', 'A permission with this name already exists.');

                return $this->render('admin/permission/new.html.twig');
            }

            $permission = (new Permission())->setName($name)->setLabel($label);
            $em->persist($permission);
            $em->flush();

            $audit->log('create', 'Permission', $permission->getId(), ['name' => $name]);

            $this->addFlash('success', 'Permission created.');

            return $this->redirectToRoute('admin_permissions');
        }

        return $this->render('admin/permission/new.html.twig');
    }

    #[Route('/{id}/delete', name: 'admin_permission_delete', methods: ['POST'])]
    public function delete(Request $request, Permission $permission, EntityManagerInterface $em, AuditService $audit): Response
    {
        $this->denyAccessUnlessGranted('permission.manage');

        if (!$this->isCsrfTokenValid('delete_permission_' . $permission->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_permissions');
        }

        if ($permission->getRoles()->count() > 0) {
            $this->addFlash('danger', 'This permission is used by roles and cannot be deleted.');

            return $this->redirectToRoute('admin_permissions');
        }

        $audit->log('delete', 'Permission', $permission->getId(), ['name' => $permission->getName()]);
        $em->remove($permission);
        $em->flush();

        $this->addFlash('success', 'Permission deleted.');

        return $this->redirectToRoute('admin_permissions');
    }
}
