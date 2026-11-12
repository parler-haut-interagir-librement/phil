<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * The root of a table of versioned documents.
 */
#[ORM\Entity]
#[ORM\Table(name: 'document')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'kind', type: 'string')]
#[ORM\DiscriminatorMap(['report' => Report::class])]
abstract class Document implements Versionable
{
    use VersionableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
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
        return ['id' => $this->id];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void
    {
    }
}
