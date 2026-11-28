<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Event;

/**
 * The changes made to a published template since it was published were abandoned for a site and a locale: its draft is
 * the published version again. Identifiers only.
 */
final readonly class TemplateDraftDiscarded
{
    public function __construct(
        public string $key,
        public string $site,
        public string $locale,
        public int $version,
        public ?string $author = null,
    ) {
    }
}
