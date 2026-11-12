<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * Keeps in its versions a value it does not map, which freezing refuses.
 */
#[ORM\Entity]
#[ORM\Table(name: 'guide')]
class Guide implements Versionable
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
        return ['nothing' => 1];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void
    {
    }
}
