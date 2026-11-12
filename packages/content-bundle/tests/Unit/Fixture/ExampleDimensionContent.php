<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Capability\BodyTrait;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Capability\ExcerptTrait;
use Phil\ContentBundle\Domain\Capability\LinkCapable;
use Phil\ContentBundle\Domain\Capability\LinkTrait;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Capability\RoutableTrait;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Capability\SeoTrait;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Capability\TemplateTrait;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowTrait;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * A row class composed the way an application composes its own: the dimension, then one trait per capability.
 */
final class ExampleDimensionContent implements DimensionContent, TemplateCapable, WorkflowCapable, SeoCapable, ExcerptCapable, LinkCapable, RoutableCapable, BodyCapable
{
    use BodyTrait;
    use ExcerptTrait;
    use LinkTrait;
    use RoutableTrait;
    use SeoTrait;
    use TemplateTrait;
    use WorkflowTrait;

    public function __construct(
        private readonly ContentRoot $root,
        private readonly DimensionKey $dimension,
    ) {
    }

    public function root(): ContentRoot
    {
        return $this->root;
    }

    public function dimension(): DimensionKey
    {
        return $this->dimension;
    }
}
