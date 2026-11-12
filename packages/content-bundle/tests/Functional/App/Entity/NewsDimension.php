<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional\App\Entity;

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

#[ORM\Entity]
#[ORM\Table(name: 'test_news_dimension')]
class NewsDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable, SeoCapable, ExcerptCapable, LinkCapable, RoutableCapable, BodyCapable
{
    use BodyTrait;
    use ExcerptTrait;
    use LinkTrait;
    use RoutableTrait;
    use SeoTrait;
    use TemplateTrait;
    use WorkflowTrait;
}
