<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;

/**
 * A second root, whose rows have the template capability only.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_page')]
class Page extends AbstractContentRoot
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: ArticleIdType::NAME)]
    private ArticleId $id;

    public function __construct()
    {
        $this->id = ArticleId::generate();
    }

    public function id(): ArticleId
    {
        return $this->id;
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return PageDimension::class;
    }
}
