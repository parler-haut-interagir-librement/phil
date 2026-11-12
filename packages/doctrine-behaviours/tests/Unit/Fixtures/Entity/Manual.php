<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * With real versions of its title and its body; its note is not versioned.
 */
#[ORM\Entity]
#[ORM\Table(name: 'manual')]
class Manual implements Versionable
{
    use VersionableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $title,
        #[ORM\Column]
        public string $body = '',
        #[ORM\Column]
        public string $note = '',
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
        return ['title' => $this->title, 'body' => $this->body];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void
    {
        \assert(\is_string($state['title']) && \is_string($state['body']));
        $this->title = $state['title'];
        $this->body = $state['body'];
    }
}
