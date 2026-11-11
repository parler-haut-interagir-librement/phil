<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentWarnings\ContentWarnings;

/**
 * A book that keeps its warnings in one column.
 */
#[ORM\Entity]
class Book
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column(type: 'phil_content_warnings', nullable: true)]
        public ?ContentWarnings $warnings = null,
    ) {
    }
}
