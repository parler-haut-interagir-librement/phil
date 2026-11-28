<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Event;

/**
 * The draft of a template was published for a site — the shared scope when `site` is empty — and a locale: the sites
 * send `version` from now on. Identifiers only.
 */
final readonly class TemplatePublished
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
