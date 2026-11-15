<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\DatedIdentifiable;
use Phil\DoctrineBehaviours\Contract\Deactivatable;
use Phil\DoctrineBehaviours\Contract\Ownable;
use Phil\DoctrineBehaviours\Contract\Publishable;
use Phil\DoctrineBehaviours\Contract\Sluggable;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\DatedIdentifierEntity;
use Phil\DoctrineBehaviours\DeactivatableEntity;
use Phil\DoctrineBehaviours\OwnableEntity;
use Phil\DoctrineBehaviours\PublishableEntity;
use Phil\DoctrineBehaviours\SluggableEntity;
use Phil\DoctrineBehaviours\VersionableEntity;

/**
 * A notice that has every behaviour added to the first four.
 */
#[ORM\Entity]
#[ORM\Table(name: 'notice')]
class Notice implements DatedIdentifiable, Deactivatable, Ownable, Publishable, Sluggable, Versionable
{
    use DatedIdentifierEntity;
    use DeactivatableEntity;
    use OwnableEntity;
    use PublishableEntity;
    use SluggableEntity;
    use VersionableEntity;

    public function __construct(
        #[ORM\Column]
        public string $title,
    ) {
    }

    public function slugSource(): string
    {
        return $this->title;
    }

    public function versionSubjectId(): string
    {
        return (string) $this->datedId();
    }

    /**
     * @return array<string, mixed>
     */
    public function versionedState(): array
    {
        return ['title' => $this->title];
    }

    /**
     * @param array<string, mixed> $state
     */
    public function applyVersionedState(array $state): void
    {
        \assert(\is_string($state['title']));
        $this->title = $state['title'];
    }
}
