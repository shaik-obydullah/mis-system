<?php

namespace App\Security\Voter;

use App\Entity\Permission;
use App\Entity\User;
use App\Repository\PermissionRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoter extends Voter
{
    public function __construct(private PermissionRepository $permissions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (str_starts_with($attribute, 'ROLE_') || str_starts_with($attribute, 'IS_')) {
            return false;
        }

        return null !== $this->permissions->findByName($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return $user->hasPermissionByName($attribute);
    }
}
