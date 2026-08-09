<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Repository\PermissionRepository;
use App\Repository\RoleRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/roles')]
class RoleController extends AbstractController
{
    #[Route('', name: 'admin_roles', methods: ['GET'])]
    public function index(RoleRepository $roles): Response
    {
        $this->denyAccessUnlessGranted('role.manage');

        return $this->render('admin/role/index.html.twig', [
            'roles' => $roles->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_role_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        PermissionRepository $permissions,
        AuditService $audit,
    ): Response {
        $this->denyAccessUnlessGranted('role.manage');

        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $label = trim((string) $request->request->get('label', ''));

            if (!str_starts_with($name, 'ROLE_') || '' === $label) {
                $this->addFlash('danger', 'Role name must start with ROLE_ and a label is required.');

                return $this->render('admin/role/new.html.twig', [
                    'permissions' => $permissions->findAllGrouped(),
                ]);
            }

            if ($em->getRepository(Role::class)->findByName($name)) {
                $this->addFlash('danger', 'A role with this name already exists.');

                return $this->render('admin/role/new.html.twig', [
                    'permissions' => $permissions->findAllGrouped(),
                ]);
            }

            $role = new Role();
            $role->setName($name);
            $role->setLabel($label);

            $this->applyPermissions($role, $request, $permissions);

            $em->persist($role);
            $em->flush();

            $audit->log('create', 'Role', $role->getId(), ['name' => $name]);

            $this->addFlash('success', 'Role created.');

            return $this->redirectToRoute('admin_roles');
        }

        return $this->render('admin/role/new.html.twig', [
            'permissions' => $permissions->findAllGrouped(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_role_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Role $role,
        EntityManagerInterface $em,
        PermissionRepository $permissions,
        AuditService $audit,
    ): Response {
        $this->denyAccessUnlessGranted('role.manage');

        if ($request->isMethod('POST')) {
            $label = trim((string) $request->request->get('label', ''));
            if ('' === $label) {
                $this->addFlash('danger', 'A label is required.');

                return $this->render('admin/role/edit.html.twig', [
                    'role' => $role,
                    'permissions' => $permissions->findAllGrouped(),
                ]);
            }

            $role->setLabel($label);
            $this->applyPermissions($role, $request, $permissions);

            $em->flush();

            $audit->log('update', 'Role', $role->getId(), ['name' => $role->getName()]);

            $this->addFlash('success', 'Role updated.');

            return $this->redirectToRoute('admin_roles');
        }

        return $this->render('admin/role/edit.html.twig', [
            'role' => $role,
            'permissions' => $permissions->findAllGrouped(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_role_delete', methods: ['POST'])]
    public function delete(Request $request, Role $role, EntityManagerInterface $em, AuditService $audit): Response
    {
        $this->denyAccessUnlessGranted('role.manage');

        if (!$this->isCsrfTokenValid('delete_role_' . $role->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_roles');
        }

        if ('ROLE_ADMIN' === $role->getName()) {
            $this->addFlash('danger', 'The ROLE_ADMIN role cannot be deleted.');

            return $this->redirectToRoute('admin_roles');
        }

        if ($role->getUsers()->count() > 0) {
            $this->addFlash('danger', 'This role is assigned to users and cannot be deleted.');

            return $this->redirectToRoute('admin_roles');
        }

        $audit->log('delete', 'Role', $role->getId(), ['name' => $role->getName()]);
        $em->remove($role);
        $em->flush();

        $this->addFlash('success', 'Role deleted.');

        return $this->redirectToRoute('admin_roles');
    }

    private function applyPermissions(Role $role, Request $request, PermissionRepository $permissions): void
    {
        $selected = $request->request->all('permissions');

        foreach ($role->getPermissions() as $existing) {
            $role->removePermission($existing);
        }

        foreach ((array) $selected as $permissionId) {
            $permission = $permissions->find((int) $permissionId);
            if ($permission) {
                $role->addPermission($permission);
            }
        }
    }
}
