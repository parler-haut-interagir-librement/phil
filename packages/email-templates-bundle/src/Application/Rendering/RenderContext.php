<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Rendering;

use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;

/**
 * Whom and what a render is for: the template, the site, the locale and the segments of the recipient, which the
 * conditional blocks test with `segment('vip')`. A template reads them as `email.key`, `email.site`, `email.locale` and
 * `email.segments`.
 */
final readonly class RenderContext
{
    /**
     * @param list<string> $segments
     */
    public function __construct(
        public TemplateKey $key,
        public Site $site,
        public Locale $locale,
        public array $segments = [],
    ) {
    }

    public function inSegment(string $segment): bool
    {
        return \in_array($segment, $this->segments, true);
    }

    /**
     * What a template reads as `email`.
     *
     * @return array{key: string, site: string, locale: string, segments: list<string>}
     */
    public function toArray(): array
    {
        return ['key' => $this->key->value, 'site' => $this->site->value, 'locale' => $this->locale->value, 'segments' => $this->segments];
    }
}
