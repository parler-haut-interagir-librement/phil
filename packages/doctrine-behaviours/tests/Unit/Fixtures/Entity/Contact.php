<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\OwnableEntity;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * Versioned and owned, with an identifier the database gives, a number: its versions name it by its text.
 */
#[ORM\Entity]
#[ORM\Table(name: 'contact')]
class Contact implements Versionable, Ownable
{
    use OwnableEntity;
    use VersionableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $phone = '',
    ) {
    }

    public function versionSubjectId(): string
    {
        return (string) $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function versionedState(): array
    {
        return ['phone' => $this->phone];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void
    {
        \assert(\is_string($state['phone']));
        $this->phone = $state['phone'];
    }
}
