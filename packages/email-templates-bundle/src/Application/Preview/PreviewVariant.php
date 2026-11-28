<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Preview;

use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * One way a template can look: for a site, in a locale, for a recipient in some segments, at a published version, as
 * its draft, or as the site sends it.
 */
final readonly class PreviewVariant
{
    /**
     * @param list<string> $segments
     */
    public function __construct(
        public TemplateKey $key,
        public Site $site,
        public Locale $locale,
        public array $segments = [],
        public ?int $version = null,
        public bool $draft = false,
    ) {
    }

    /**
     * The segments as one text, `vip,first-order`, empty for none: how the preview addresses a variant.
     */
    public function segmentList(): string
    {
        return implode(',', $this->segments);
    }
}
