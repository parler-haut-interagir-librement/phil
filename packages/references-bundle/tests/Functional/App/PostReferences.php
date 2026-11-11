<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Functional\App;

use Phil\ReferencesBundle\Application\Link;
use Phil\ReferencesBundle\Application\ReferenceCollector;
use Phil\ReferencesBundle\Tests\Functional\App\Entity\Post;
use Phil\Resource\ResourceReference;

/**
 * The link of a post to its cover image.
 */
final readonly class PostReferences implements ReferenceCollector
{
    public function supports(object $source): bool
    {
        return $source instanceof Post;
    }

    public function sourceOf(object $source): ResourceReference
    {
        \assert($source instanceof Post);

        return ResourceReference::of('post', (string) $source->id);
    }

    public function collect(object $source): iterable
    {
        \assert($source instanceof Post);

        yield new Link('cover', ResourceReference::of('image', $source->cover));
    }
}
