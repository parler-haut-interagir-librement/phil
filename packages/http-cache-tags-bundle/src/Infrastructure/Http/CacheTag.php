<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Infrastructure\Http;

use Attribute;

/**
 * Tags of a controller's responses. A `{name}` is replaced by the request attribute of that name — a route
 * parameter: `#[CacheTag('page:{id}')]`; a tag whose attribute is missing is left out.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class CacheTag
{
    /** @var array<array-key, string> */
    public array $tags;

    public function __construct(string ...$tags)
    {
        $this->tags = $tags;
    }
}
