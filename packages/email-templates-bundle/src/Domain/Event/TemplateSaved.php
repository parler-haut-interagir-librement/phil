<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Event;

/**
 * The draft of a template was saved for a site — the shared scope when `site` is empty — and a locale: `version` is the
 * version it belongs to, `revision` counts its saves. When it is a copy of a published version, `revertedFrom` names
 * that version. Identifiers only: no source, no value.
 */
final readonly class TemplateSaved
{
    public function __construct(
        public string $key,
        public string $site,
        public string $locale,
        public int $version,
        public int $revision,
        public ?string $author = null,
        public ?int $revertedFrom = null,
    ) {
    }
}
