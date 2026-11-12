<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Phil\MediaBundle\Domain\ValueObject\MediaId;

/**
 * A record that shows media: a cover that may be emptied, a banner that may not, and a title that is no medium.
 */
#[ORM\Entity]
#[ORM\Table(name: 'post')]
class Post
{
    #[ORM\Column]
    public string $title = '';

    public function __construct(
        #[ORM\Id]
        #[ORM\Column]
        public int $id,
        #[ORM\Column(type: 'phil_media')]
        public MediaId $banner,
        #[ORM\Column(type: 'phil_media', nullable: true)]
        public ?MediaId $cover = null
    ) {
    }
}
