<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Capability\TemplateTrait;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;

/**
 * An unmapped parent that composes a capability: the trait's private properties are declared here, and the
 * mapping must reach them through this class.
 */
abstract class TemplatedDimension extends AbstractDimensionContent implements TemplateCapable
{
    use TemplateTrait;
}
