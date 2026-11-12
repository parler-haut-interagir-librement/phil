<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;
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
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;

/**
 * A row class as an application writes one: the base class, every capability of the bundle, and no mapping of
 * its own.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_article_dimension')]
class ArticleDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable, SeoCapable, ExcerptCapable, LinkCapable, RoutableCapable, BodyCapable
{
    use BodyTrait;
    use ExcerptTrait;
    use LinkTrait;
    use RoutableTrait;
    use SeoTrait;
    use TemplateTrait;
    use WorkflowTrait;
}
