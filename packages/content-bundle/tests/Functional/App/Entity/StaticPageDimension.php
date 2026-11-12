<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Capability\BodyTrait;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Capability\RoutableTrait;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowTrait;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;

/**
 * No template capability: the default template renders it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_static_page_dimension')]
class StaticPageDimension extends AbstractDimensionContent implements WorkflowCapable, RoutableCapable, BodyCapable
{
    use BodyTrait;
    use RoutableTrait;
    use WorkflowTrait;
}
