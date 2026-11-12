<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Unit\Fixtures;

use Phil\SearchBundle\Application\DocumentProvider;
use Phil\SearchBundle\Domain\ValueObject\Document;

class Articles implements DocumentProvider
{
    /**
     * @param list<Article> $articles
     */
    public function __construct(
        public array $articles = [],
    ) {
    }

    public function index(): string
    {
        return 'articles';
    }

    public function supports(object $resource): bool
    {
        return $resource instanceof Article;
    }

    public function document(object $resource): ?Document
    {
        \assert($resource instanceof Article);

        return $resource->published ? Document::of('articles', $resource->id, ['title' => $resource->title, 'body' => $resource->body], ['locale' => $resource->locale, 'tags' => $resource->tags]) : null;
    }

    public function id(object $resource): string
    {
        \assert($resource instanceof Article);

        return $resource->id;
    }

    public function all(): iterable
    {
        return $this->articles;
    }
}
