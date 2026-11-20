<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * Versioned, and owned and blamed by hand, with none of the owner and blame fields mapped: the version eraser leaves
 * its links to a person out. A note, a score and a free-form value are in its versions.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ledger')]
class Ledger implements Versionable, Ownable, Blameable
{
    use VersionableEntity;

    /**
     * @param array<array-key, mixed>|null $data
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $note = '',
        #[ORM\Column(type: Types::FLOAT)]
        public float $score = 0.0,
        #[ORM\Column(type: Types::JSON, nullable: true)]
        public ?array $data = null,
    ) {
    }

    public function versionSubjectId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function versionedState(): array
    {
        return ['note' => $this->note, 'score' => $this->score, 'data' => $this->data];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void
    {
        \assert(\is_string($state['note']) && \is_float($state['score']) && (null === $state['data'] || \is_array($state['data'])));
        $this->note = $state['note'];
        $this->score = $state['score'];
        $this->data = $state['data'];
    }

    public function ownerId(): ?OwnerId
    {
        return null;
    }

    public function claimOwner(OwnerId $owner): void
    {
    }

    public function transferOwner(?OwnerId $owner): void
    {
    }

    public function createdBy(): ?string
    {
        return null;
    }

    public function updatedBy(): ?string
    {
        return null;
    }

    public function blameCreated(string $who): void
    {
    }

    public function blameUpdated(string $who): void
    {
    }
}
