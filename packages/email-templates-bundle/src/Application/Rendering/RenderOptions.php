<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Rendering;

use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;

/**
 * What a render may choose: the site — the current one by default —, the locale — the default locale —, the segments
 * of the recipient, and a version of the site's template rather than its latest.
 */
final readonly class RenderOptions
{
    /**
     * @param list<string> $segments
     */
    public function __construct(
        public ?Site $site = null,
        public ?string $locale = null,
        public array $segments = [],
        public ?int $version = null,
    ) {
    }
}
