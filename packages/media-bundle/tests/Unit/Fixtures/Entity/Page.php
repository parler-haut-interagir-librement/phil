<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A record whose only medium column comes from its mapped superclass, and that embeds a value holding one.
 */
#[ORM\Entity]
#[ORM\Table(name: 'page')]
class Page extends Dated
{
    #[ORM\Embedded(class: Seo::class)]
    public Seo $seo;

    public function __construct(#[ORM\Id]
        #[ORM\Column]
        public int $id)
    {
        $this->seo = new Seo();
    }
}
