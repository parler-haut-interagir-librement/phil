<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Fixtures;

final class Article
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $body = '',
        public string $locale = 'fr',
        public array $tags = [],
        public bool $published = true,
    ) {
    }
}
