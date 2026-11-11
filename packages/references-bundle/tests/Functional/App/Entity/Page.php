<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Functional\App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A page whose blocks, in JSON, point to images.
 */
#[ORM\Entity]
#[ORM\Table(name: 'page')]
class Page
{
    /**
     * @param list<array{type: string, image?: string}> $blocks
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        public string $id,
        #[ORM\Column(type: Types::JSON)]
        public array $blocks = [],
    ) {
    }
}
