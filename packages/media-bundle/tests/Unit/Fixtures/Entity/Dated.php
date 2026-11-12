<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\MediaBundle\Domain\ValueObject\MediaId;

/**
 * A mapped superclass whose medium column is counted on the entities that extend it, never on itself.
 */
#[ORM\MappedSuperclass]
abstract class Dated
{
    #[ORM\Column(type: 'phil_media', nullable: true)]
    public ?MediaId $stamp = null;
}
