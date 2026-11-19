<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Functional\App;

use Override;
use Phil\DoctrineBehavioursBundle\Tests\Functional\App\Entity\Page;
use Phil\SearchBundle\Application\DocumentProvider;
use Phil\SearchBundle\Domain\ValueObject\Document;

/**
 * The pages in the index `pages`, by their identifier.
 */
final readonly class PageDocuments implements DocumentProvider
{
    #[Override]
    public function index(): string
    {
        return 'pages';
    }

    #[Override]
    public function supports(object $resource): bool
    {
        return $resource instanceof Page;
    }

    #[Override]
    public function document(object $resource): Document
    {
        return Document::of('pages', $this->id($resource), ['title' => $this->id($resource)]);
    }

    #[Override]
    public function id(object $resource): string
    {
        \assert($resource instanceof Page);

        return $resource->id;
    }

    #[Override]
    public function all(): iterable
    {
        return [];
    }
}
