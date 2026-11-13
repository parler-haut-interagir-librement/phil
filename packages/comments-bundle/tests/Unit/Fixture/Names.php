<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Fixture;

use Override;
use Phil\CommentsBundle\Application\AuthorDirectory;
use Phil\Resource\ResourceReference;

/**
 * Ada and Bob by name; anyone else unknown.
 */
final readonly class Names implements AuthorDirectory
{
    #[Override]
    public function name(ResourceReference $author): ?string
    {
        return ['user:ada' => 'Ada', 'user:bob' => 'Bob'][$author->toString()] ?? null;
    }
}
