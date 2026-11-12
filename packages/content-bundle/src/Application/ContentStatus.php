<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Application;

use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * Where one locale of a root stands: the latest draft version, its workflow place and slug, and the version
 * readers are served, if any.
 */
final readonly class ContentStatus
{
    public function __construct(
        public ContentReference $reference,
        public string $locale,
        public int $draftVersion,
        public ?string $place,
        public ?string $slug,
        public ?int $liveVersion,
    ) {
    }
}
