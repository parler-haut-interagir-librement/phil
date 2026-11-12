<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;

/**
 * A root whose rows compose both capabilities of the bundle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_probe_article')]
class Article extends AbstractContentRoot
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: ArticleIdType::NAME)]
    private ArticleId $id;

    #[ORM\Column(name: 'genre', nullable: true)]
    private ?string $genre = null;

    public function __construct()
    {
        $this->id = ArticleId::generate();
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return ArticleDimension::class;
    }

    public function id(): ArticleId
    {
        return $this->id;
    }

    public function genre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(?string $genre): void
    {
        $this->genre = $genre;
    }
}
