<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture;

use Override;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;

/**
 * @extends IdentifierType<ArticleId>
 */
final class ArticleIdType extends IdentifierType
{
    public const string NAME = 'phil_content_probe_article_id';

    #[Override]
    protected function identifierClass(): string
    {
        return ArticleId::class;
    }
}
