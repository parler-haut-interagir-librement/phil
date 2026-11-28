<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Domain\Status\PublicationState;
use Phil\EmailTemplatesBundle\Domain\Status\TemplateOrigin;

/**
 * One template in one locale, as a site sends it: whether it is the site's own published version, the shared one or the
 * default file — `null` when nothing is sent in exactly this locale, so another locale of the chain is —; the version
 * sent, for a published one; and where the scope's own draft stands, `null` when it has none.
 */
final readonly class CatalogRow
{
    public function __construct(
        public string $key,
        public string $locale,
        public ?TemplateOrigin $origin,
        public ?int $version = null,
        public ?DateTimeImmutable $savedAt = null,
        public ?PublicationState $state = null,
    ) {
    }
}
