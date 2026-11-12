<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Capability\TemplateTrait;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;

#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_page_dimension')]
class PageDimension extends AbstractDimensionContent implements TemplateCapable
{
    use TemplateTrait;
}
