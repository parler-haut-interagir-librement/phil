<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;

/**
 * A row class that no declared root names.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_orphan_dimension')]
class OrphanDimension extends AbstractDimensionContent
{
}
