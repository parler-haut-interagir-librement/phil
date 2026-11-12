<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application\DataMapper;

use Override;
use Phil\ContentBundle\Application\Pipeline\DataMapper;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;

/**
 * Writes `RoutableCapable` on the localized row, from the `slug` key: an address per language. `null`
 * clears the slug.
 *
 * The slug is taken as submitted, never derived from a title here: which words make an address, and whether
 * one is free in its locale, are the application's to decide before it submits one.
 */
final readonly class RoutableDataMapper implements DataMapper
{
    #[Override]
    public function map(DimensionContent $unlocalized, DimensionContent $localized, array $data): void
    {
        if (!$localized instanceof RoutableCapable || !SubmittedData::has($data, 'slug')) {
            return;
        }

        $slug = SubmittedData::string($data, 'slug');

        try {
            $localized->setSlug(null === $slug ? null : Slug::fromString($slug));
        } catch (InvalidSlug $invalidSlug) {
            throw InvalidContent::because($invalidSlug->getMessage());
        }
    }
}
