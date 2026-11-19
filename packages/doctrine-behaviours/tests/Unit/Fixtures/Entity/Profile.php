<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\BlameableEntity;
use Phil\DoctrineBehaviours\Contract\Blameable;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\OwnableEntity;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * Versioned, owned and blamed, with personal information in its versions: a name and an address; its biography is
 * not personal.
 */
#[ORM\Entity]
#[ORM\Table(name: 'profile')]
class Profile implements Versionable, Ownable, Blameable
{
    use BlameableEntity;
    use OwnableEntity;
    use VersionableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column]
        public string $name = '',
        #[ORM\Column]
        public string $email = '',
        #[ORM\Column]
        public string $bio = '',
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
        return ['name' => $this->name, 'email' => $this->email, 'bio' => $this->bio];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void
    {
        \assert(\is_string($state['name']) && \is_string($state['email']) && \is_string($state['bio']));
        $this->name = $state['name'];
        $this->email = $state['email'];
        $this->bio = $state['bio'];
    }
}
