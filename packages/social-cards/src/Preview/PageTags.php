<?php

declare(strict_types=1);

namespace Phil\SocialCards\Preview;

/**
 * The tags read in a page's head: its address, its `<title>`, its canonical link, and the content of each `<meta>`
 * by its `property` or `name`, in lower case, in the order of the page.
 */
final readonly class PageTags
{
    /**
     * @param array<string, list<string>> $meta
     */
    public function __construct(
        public string $url,
        public ?string $title = null,
        public ?string $canonical = null,
        public array $meta = [],
    ) {
    }

    /**
     * The first content of a tag, or null.
     */
    public function get(string $key): ?string
    {
        return $this->meta[strtolower($key)][0] ?? null;
    }

    /**
     * Every content of a tag, in the order of the page.
     *
     * @return list<string>
     */
    public function all(string $key): array
    {
        return $this->meta[strtolower($key)] ?? [];
    }

    /**
     * The first of the tags the page has, in the order given.
     */
    public function first(string ...$keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->get($key);

            if (null !== $value) {
                return $value;
            }
        }

        return null;
    }
}
