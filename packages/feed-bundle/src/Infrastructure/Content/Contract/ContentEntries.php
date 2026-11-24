<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\Content\Contract;

use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\LocaleDimensions\Locale;

/**
 * What a content feed reads of `phil/content-bundle`: the latest published contents of a kind in a locale, and their
 * absolute addresses.
 *
 * @internal
 */
interface ContentEntries
{
    /**
     * The contents of the kind the locale publishes itself, with a slug, the most recently published first.
     *
     * @return list<PublishedContent>
     */
    public function latest(string $kind, Locale $locale, int $offset, int $limit): array;

    /**
     * The absolute address of each content that has one, by the string of its reference.
     *
     * @param list<PublishedContent> $contents
     *
     * @return array<string, string>
     */
    public function addresses(array $contents, Locale $locale): array;
}
