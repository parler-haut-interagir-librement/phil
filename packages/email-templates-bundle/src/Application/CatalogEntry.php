<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Domain\Status\PublicationState;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * The draft a site saved of one template in one locale, without its sources: where it stands, and the version the sites
 * send, `null` while none is published.
 */
final readonly class CatalogEntry
{
    /**
     * @param int<1, max>      $version
     * @param int<1, max>|null $liveVersion
     */
    public function __construct(
        public TemplateKey $key,
        public Site $site,
        public Locale $locale,
        public PublicationState $state,
        public int $version,
        public DateTimeImmutable $savedAt,
        public ?string $author = null,
        public ?int $liveVersion = null,
    ) {
    }
}
