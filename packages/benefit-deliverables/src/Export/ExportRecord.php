<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Export;

use DateTimeImmutable;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use Phil\BenefitDeliverables\Period;

/**
 * The trace of one shipping export: who asked for it and when, which period and benefit, how many rows it holds, how
 * many members were left out for want of an address, until when its file is kept, and when the file was erased.
 */
final readonly class ExportRecord
{
    /**
     * @param string $id          32 lower-case hexadecimal digits
     * @param string $requestedBy the identifier of the person on the team who asked for it
     */
    public function __construct(
        public string $id,
        public Period $period,
        public string $benefit,
        public string $requestedBy,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public int $rows,
        public int $withoutAddress,
        public ?DateTimeImmutable $fileErasedAt = null,
    ) {
        if (!self::isId($id)) {
            throw InvalidDeliverable::because('An export is identified by 32 lower-case hexadecimal digits.');
        }

        if ('' === trim($requestedBy) || \strlen($requestedBy) > 255) {
            throw InvalidDeliverable::because('Who asked for an export is named by 1 to 255 characters.');
        }

        if ($rows < 0 || $withoutAddress < 0) {
            throw InvalidDeliverable::because('An export counts zero rows or more.');
        }

        if ($expiresAt <= $createdAt) {
            throw InvalidDeliverable::because('An export expires after it was made.');
        }
    }

    public static function isId(string $id): bool
    {
        return 1 === preg_match('/^[0-9a-f]{32}$/D', $id);
    }

    public function isAvailable(DateTimeImmutable $at): bool
    {
        return !$this->fileErasedAt instanceof DateTimeImmutable && $at < $this->expiresAt;
    }

    public function withFileErasedAt(DateTimeImmutable $at): self
    {
        if ($this->fileErasedAt instanceof DateTimeImmutable) {
            return $this;
        }

        return new self($this->id, $this->period, $this->benefit, $this->requestedBy, $this->createdAt, $this->expiresAt, $this->rows, $this->withoutAddress, $at);
    }
}
