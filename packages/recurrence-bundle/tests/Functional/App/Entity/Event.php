<?php

declare(strict_types=1);

namespace Phil\RecurrenceBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\Recurrence\RecurrenceRule;

#[ORM\Entity]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'phil_rrule', nullable: true)]
        public ?RecurrenceRule $rule = null,
    ) {
    }
}
