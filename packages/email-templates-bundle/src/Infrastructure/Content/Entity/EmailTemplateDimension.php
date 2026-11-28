<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Content\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Capability\TemplateTrait;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowTrait;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;

/**
 * One locale, stage and version of a template. The localized row holds the template in its field values
 * (`TemplateCapable`), written by `TemplatePayload`; the workflow place and the publication date are the editorial
 * workflow's (`WorkflowCapable`). `phil/content-bundle` maps the dimension, the capabilities and the root.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_email_template_dimension')]
class EmailTemplateDimension extends AbstractDimensionContent implements TemplateCapable, WorkflowCapable
{
    use TemplateTrait;
    use WorkflowTrait;
}
