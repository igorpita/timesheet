<?php

declare(strict_types=1);

namespace OCA\OABTimesheet\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

class TimeEntry extends Entity implements JsonSerializable {
    protected ?string $userUid = null;
    protected ?string $sourceType = null;
    protected ?string $externalId = null;
    protected ?string $externalTaskId = null;
    protected ?string $externalTitle = null;
    protected ?string $workDate = null;
    protected ?int $durationMinutes = null;
    protected ?string $description = null;
    protected ?string $status = 'submitted';
    protected ?string $createdBy = null;
    protected ?string $updatedBy = null;
    protected ?string $approvedBy = null;
    protected ?string $approvedAt = null;
    protected ?string $originEntryId = null;
    protected int $version = 1;
    protected ?string $createdAt = null;
    protected ?string $updatedAt = null;

    public function __construct() {
        $this->addType('id', 'string');
    }

    public static function fromRow(array $row): static {
        $instance = parent::fromRow($row);
        if (isset($row['id'])) {
            $instance->id = (string)$row['id'];
        }
        return $instance;
    }

    public function getId(): string {
        return (string)($this->id ?? '');
    }

    public function setId($id): self {
        $this->id = (string)$id;
        return $this;
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->getId(),
            'userUid' => $this->getUserUid(),
            'sourceType' => $this->getSourceType(),
            'externalId' => $this->getExternalId(),
            'externalTaskId' => $this->getExternalTaskId(),
            'externalTitle' => $this->getExternalTitle(),
            'workDate' => $this->getWorkDate(),
            'durationMinutes' => $this->getDurationMinutes(),
            'durationFormatted' => sprintf('%02dh%02d', intdiv($this->getDurationMinutes() ?? 0, 60), ($this->getDurationMinutes() ?? 0) % 60),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
            'createdBy' => $this->getCreatedBy(),
            'updatedBy' => $this->getUpdatedBy(),
            'approvedBy' => $this->getApprovedBy(),
            'approvedAt' => $this->getApprovedAt(),
            'originEntryId' => $this->getOriginEntryId(),
            'version' => $this->getVersion(),
            'createdAt' => $this->getCreatedAt(),
            'updatedAt' => $this->getUpdatedAt(),
        ];
    }
}

