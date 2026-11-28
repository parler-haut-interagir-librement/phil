<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Domain\Event;

/**
 * A template was taken offline for a site and a locale: the site sends the shared template, or the default file, again.
 * Its draft and its versions are kept. Identifiers only.
 */
final readonly class TemplateReset
{
    public function __construct(
        public string $key,
        public string $site,
        public string $locale,
        public ?string $author = null,
    ) {
    }
}
