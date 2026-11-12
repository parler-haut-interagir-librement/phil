<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\MediaBundle\Domain\ValueObject\MediaId;

/**
 * An embeddable whose medium column is counted on the entity that embeds it.
 */
#[ORM\Embeddable]
class Seo
{
    #[ORM\Column(type: 'phil_media', nullable: true)]
    public ?MediaId $image = null;
}
