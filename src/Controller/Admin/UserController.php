<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users')]
class UserController extends AbstractController
{
    #[Route('', name: 'admin_users', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $users->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AuditService $audit,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $fullName = trim((string) $request->request->get('full_name', ''));
            $role = (string) $request->request->get('role', 'ROLE_STAFF');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || '' === $fullName) {
                $this->addFlash('danger', 'Invalid input: valid email, name and a password of 6+ characters are required.');

                return $this->render('admin/user/new.html.twig');
            }

            if ($em->getRepository(User::class)->findOneByEmail($email)) {
                $this->addFlash('danger', 'A user with this email already exists.');

                return $this->render('admin/user/new.html.twig');
            }

            $user = new User();
            $user->setEmail($email);
            $user->setFullName($fullName);
            $user->setRole($role);
            $user->setPassword($hasher->hashPassword($user, $password));
            $user->setActive((bool) $request->request->get('active', false));

            $em->persist($user);
            $em->flush();

            $audit->log('create', 'User', $user->getId(), ['email' => $email, 'role' => $role]);
            $this->addFlash('success', 'User created.');

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        AuditService $audit,
    ): Response {
        if ($request->isMethod('POST')) {
            $user->setEmail(trim((string) $request->request->get('email', $user->getEmail() ?? '')));
            $user->setFullName(trim((string) $request->request->get('full_name', $user->getFullName() ?? '')));
            $user->setRole((string) $request->request->get('role', $user->getRole()));
            $user->setActive((bool) $request->request->get('active', false));

            $newPassword = (string) $request->request->get('password', '');
            if ('' !== $newPassword) {
                if (strlen($newPassword) < 6) {
                    $this->addFlash('danger', 'Password must be at least 6 characters.');

                    return $this->render('admin/user/edit.html.twig', ['user' => $user]);
                }
                $user->setPassword($hasher->hashPassword($user, $newPassword));
            }

            $em->flush();

            $audit->log('update', 'User', $user->getId(), ['role' => $user->getRole()]);
            $this->addFlash('success', 'User updated.');

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/user/edit.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em, AuditService $audit): Response
    {
        if (!$this->isCsrfTokenValid('delete_user_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_users');
        }

        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'You cannot delete your own account.');

            return $this->redirectToRoute('admin_users');
        }

        $audit->log('delete', 'User', $user->getId(), ['email' => $user->getEmail()]);
        $em->remove($user);
        $em->flush();

        $this->addFlash('success', 'User deleted.');

        return $this->redirectToRoute('admin_users');
    }
}
