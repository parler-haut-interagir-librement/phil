<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_note_dimension')]
#[ORM\UniqueConstraint(name: 'phil_content_probe_note_template', columns: ['template_key'])]
class NoteDimension extends TemplatedDimension
{
}
