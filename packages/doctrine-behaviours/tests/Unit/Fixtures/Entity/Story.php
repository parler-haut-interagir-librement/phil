<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\Publishable;
use Phil\DoctrineBehaviours\PublishableEntity;

/**
 * Published through the workflow.
 */
#[ORM\Entity]
#[ORM\Table(name: 'story')]
class Story implements Publishable
{
    use PublishableEntity;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
    ) {
    }
}
