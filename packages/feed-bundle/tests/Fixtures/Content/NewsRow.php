<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures\Content;

use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Capability\BodyTrait;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Capability\ExcerptTrait;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Capability\SeoTrait;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowTrait;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;

/**
 * A row of news as an application composes one: an excerpt, SEO, the workflow and a body.
 */
final class NewsRow implements DimensionContent, ExcerptCapable, SeoCapable, WorkflowCapable, BodyCapable
{
    use BodyTrait;
    use ExcerptTrait;
    use SeoTrait;
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
