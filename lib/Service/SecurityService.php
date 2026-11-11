<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Service;

use OCA\OABTimesheet\Db\TimeEntry;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

class SecurityService {
    public function __construct(
        private IUserSession $userSession,
        private IGroupManager $groupManager
    ) {}

    public function getCurrentUser(): ?IUser {
        return $this->userSession->getUser();
    }

    public function getCurrentUserUid(): string {
        $user = $this->getCurrentUser();
        if ($user === null) {
            throw new \RuntimeException('Usuário não autenticado no Nextcloud.');
        }
        return $user->getUID();
    }

    public function isManager(): bool {
        $user = $this->getCurrentUser();
        if ($user === null) {
            return false;
        }

        // Administradores Nextcloud ou membros do grupo gestor_ti / ti_gestao
        if ($this->groupManager->isAdmin($user->getUID())) {
            return true;
        }

        $managerGroups = ['admin', 'gestor_ti', 'ti_gestao', 'coordenacao_ti'];
        foreach ($managerGroups as $groupName) {
            $group = $this->groupManager->get($groupName);
            if ($group !== null && $group->inGroup($user)) {
                return true;
            }
        }

        return false;
    }

    public function canEditEntry(TimeEntry $entry): bool {
        $currentUserUid = $this->getCurrentUserUid();

        // O próprio autor pode editar
        if ($entry->getUserUid() === $currentUserUid) {
            return true;
        }

        // Gestor pode editar (com trilha de auditoria)
        return $this->isManager();
    }

    public function canDeleteEntry(TimeEntry $entry): bool {
        $currentUserUid = $this->getCurrentUserUid();

        // O próprio autor pode excluir ou gestor
        return $entry->getUserUid() === $currentUserUid || $this->isManager();
    }
}

