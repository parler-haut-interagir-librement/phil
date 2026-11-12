<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application\Contract;

/**
 * Gives the share metadata of the current page from a model of the application — a content page, an article, a
 * product —, read before what the request sets itself: the keys of `ShareMetadata::KEYS`, `card_template` and `card`.
 * A source with nothing to say returns an empty array.
 */
interface ShareMetadataSource
{
    /**
     * @return array<string, mixed>
     */
    public function values(): array;
}
