<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Infrastructure\Twig;

use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Twig\Attribute\AsTwigFunction;

/**
 * `{{ cache_tag('page:' ~ page.id) }}`: a template tags the response it renders, and writes nothing.
 */
final readonly class CacheTagExtension
{
    public function __construct(
        private ResponseTags $tags,
    ) {
    }

    #[AsTwigFunction(name: 'cache_tag')]
    public function tag(string ...$tags): string
    {
        $this->tags->add(...$tags);

        return '';
    }
}
