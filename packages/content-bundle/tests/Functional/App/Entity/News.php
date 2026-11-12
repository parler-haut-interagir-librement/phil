<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional\App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;

/**
 * A root identified by a UUIDv7 it generates itself: resource key `news`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_news')]
class News extends AbstractContentRoot
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: NewsIdType::NAME)]
    private NewsId $id;

    public function __construct()
    {
        $this->id = NewsId::generate();
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return NewsDimension::class;
    }

    public function id(): NewsId
    {
        return $this->id;
    }
}
