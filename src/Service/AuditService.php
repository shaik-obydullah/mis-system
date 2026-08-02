<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ?TokenStorageInterface $tokenStorage = null,
    ) {
    }

    public function log(string $action, string $entity, ?int $entityId = null, array $changes = [], ?string $ip = null): void
    {
        $user = null;
        $token = $this->tokenStorage?->getToken();

        if ($token) {
            $identity = $token->getUser();
            if ($identity instanceof User) {
                $user = $identity;
            }
        }

        $log = new AuditLog();
        $log->setUser($user);
        $log->setAction($action);
        $log->setEntity($entity);
        $log->setEntityId($entityId);
        $log->setChanges($changes);
        $log->setIp($ip);

        $this->em->persist($log);
        $this->em->flush();
    }
}
