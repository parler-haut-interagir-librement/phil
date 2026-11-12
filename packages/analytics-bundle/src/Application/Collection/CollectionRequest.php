<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Application\Collection;

use SensitiveParameter;

/**
 * What collection reads of one HTTP request, and nothing more. The client address is used in memory, for the limiter's
 * digest and the country, and never kept.
 */
final readonly class CollectionRequest
{
    /**
     * @param ?string $origin               the `Origin` header
     * @param ?string $referer              the `Referer` header
     * @param ?string $doNotTrack           the `DNT` header
     * @param ?string $globalPrivacyControl the `Sec-GPC` header
     * @param string  $body                 the body the collection script sends, as text
     */
    public function __construct(
        public ?string $origin,
        public ?string $referer,
        #[SensitiveParameter]
        public string $clientIp,
        public string $userAgent,
        public ?string $doNotTrack,
        public ?string $globalPrivacyControl,
        public string $body,
    ) {
    }
}
