<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Version;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\BlameableEntity;
use Phil\DoctrineBehaviours\Contract\Blameable;

/**
 * A frozen version of a record: its number, what it held, and when it was frozen. Written once by `Versions`, never
 * edited. It is `Blameable`, so `created_by` names who froze it and `BlameEraser` reaches it. A record is designated
 * by its root table and its identifier, which last through a rename of its class.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_record_version')]
#[ORM\UniqueConstraint(name: 'phil_record_version_number', columns: ['subject_table', 'subject_id', 'version_number'])]
class RecordVersion implements Blameable
{
    use BlameableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    protected ?int $id = null;

    /**
     * @param array<string, mixed> $state
     */
    public function __construct(
        #[ORM\Column(name: 'subject_table', type: Types::STRING, length: 128)]
        protected string $subjectTable,
        #[ORM\Column(name: 'subject_id', type: Types::STRING, length: 255)]
        protected string $subjectId,
        #[ORM\Column(name: 'version_number', type: Types::INTEGER)]
        protected int $number,
        #[ORM\Column(name: 'state', type: Types::JSON)]
        protected array $state,
        #[ORM\Column(name: 'frozen_at', type: Types::DATETIME_IMMUTABLE)]
        protected DateTimeImmutable $frozenAt,
    ) {
    }

    public function subjectTable(): string
    {
        return $this->subjectTable;
    }

    public function subjectId(): string
    {
        return $this->subjectId;
    }

    public function number(): int
    {
        return $this->number;
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return $this->state;
    }

    public function frozenAt(): DateTimeImmutable
    {
        return $this->frozenAt;
    }
}
