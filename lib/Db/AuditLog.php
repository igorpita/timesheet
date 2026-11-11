<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class AuditLog extends Entity implements JsonSerializable {
    protected ?string $entryId = null;
    protected ?string $userUid = null;
    protected ?string $action = null;
    protected ?string $changes = null;
    protected ?string $createdAt = null;

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'entryId' => $this->getEntryId(),
            'userUid' => $this->getUserUid(),
            'action' => $this->getAction(),
            'changes' => json_decode($this->getChanges() ?? '{}', true),
            'createdAt' => $this->getCreatedAt(),
        ];
    }
}

