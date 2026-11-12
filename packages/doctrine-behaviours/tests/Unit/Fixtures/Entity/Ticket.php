<?php

declare(strict_types=1);

namespace Phil\DoctrineBehaviours\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\DoctrineBehaviours\Contract\DatedIdentifiable;
use Phil\DoctrineBehaviours\DatedIdentifierEntity;

/**
 * Identified by the time it was made.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ticket')]
class Ticket implements DatedIdentifiable
{
    use DatedIdentifierEntity;

    public function __construct(
        #[ORM\Column]
        public string $subject = '',
    ) {
    }
}
