<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Tests\Unit\Fixtures;

use Phil\ReferencesBundle\Application\Link;
use Phil\ReferencesBundle\Application\ReferenceCollector;
use Phil\Resource\ResourceReference;

final class PageCollector implements ReferenceCollector
{
    public function supports(object $source): bool
    {
        return $source instanceof Document && 'page' === $source->kind;
    }

    public function sourceOf(object $source): ResourceReference
    {
        \assert($source instanceof Document);

        return ResourceReference::of('page', $source->id);
    }

    public function collect(object $source): iterable
    {
        \assert($source instanceof Document);

        foreach ($source->images as $index => $image) {
            yield new Link('blocks.'.$index.'.image', ResourceReference::of('image', $image));
        }
    }
}
