<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class SyncQueue extends Entity implements JsonSerializable {
    protected ?string $entryId = null;
    protected ?string $targetSystem = null;
    protected ?string $syncStatus = 'pending';
    protected int $attempts = 0;
    protected ?string $lastError = null;
    protected ?string $idempotencyKey = null;
    protected ?string $createdAt = null;
    protected ?string $syncedAt = null;

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'entryId' => $this->getEntryId(),
            'targetSystem' => $this->getTargetSystem(),
            'syncStatus' => $this->getSyncStatus(),
            'attempts' => $this->getAttempts(),
            'lastError' => $this->getLastError(),
            'idempotencyKey' => $this->getIdempotencyKey(),
            'createdAt' => $this->getCreatedAt(),
            'syncedAt' => $this->getSyncedAt(),
        ];
    }
}

