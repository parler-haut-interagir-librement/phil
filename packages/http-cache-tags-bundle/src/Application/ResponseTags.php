<?php

declare(strict_types=1);

namespace Phil\HttpCacheTagsBundle\Application;

use Phil\HttpCacheTagsBundle\Domain\ValueObject\CacheTag;

/**
 * The tags of the response being rendered: added by the controller, its attribute and the templates, written into a
 * header when the response leaves; reset between requests.
 */
final class ResponseTags
{
    /** @var array<string, CacheTag> */
    private array $tags = [];

    public function add(string ...$tags): void
    {
        foreach (CacheTag::list($tags) as $tag) {
            $this->tags[$tag->value] = $tag;
        }
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_keys($this->tags);
    }

    public function reset(): void
    {
        $this->tags = [];
    }
}
