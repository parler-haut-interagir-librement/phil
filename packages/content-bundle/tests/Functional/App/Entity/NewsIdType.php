<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional\App\Entity;

use Override;
use Phil\DddBundle\Infrastructure\Doctrine\IdentifierType;

/**
 * @extends IdentifierType<NewsId>
 */
final class NewsIdType extends IdentifierType
{
    public const string NAME = 'phil_content_test_news_id';

    #[Override]
    protected function identifierClass(): string
    {
        return NewsId::class;
    }
}
