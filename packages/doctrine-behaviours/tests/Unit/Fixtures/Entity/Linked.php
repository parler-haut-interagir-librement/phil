<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * Keeps an association in its versions, by the identifier.
 */
#[ORM\Entity]
#[ORM\Table(name: 'linked')]
class Linked implements Versionable
{
    use VersionableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\ManyToOne(targetEntity: Note::class)]
        #[ORM\JoinColumn(nullable: true)]
        public ?Note $note = null,
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
        return ['note' => $this->note?->id];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void
    {
    }
}
