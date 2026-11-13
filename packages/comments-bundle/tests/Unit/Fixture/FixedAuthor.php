<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Fixture;

use Override;
use Phil\CommentsBundle\Application\AuthorProvider;
use Phil\Resource\ResourceReference;

/**
 * Whoever the test says is commenting.
 */
final class FixedAuthor implements AuthorProvider
{
    public function __construct(
        public ?ResourceReference $author = null,
    ) {
    }

    #[Override]
    public function author(): ?ResourceReference
    {
        return $this->author;
    }
}
