<?php

declare(strict_types=1);

namespace Phil\BounceParser;

/**
 * The machine-readable part of an abuse report (RFC 5965, section 3.1), `message/feedback-report`.
 */
final readonly class FeedbackReport
{
    /**
     * @param list<string> $originalRcptTo the recipients of the message reported, often hidden by the provider
     * @param list<string> $reportedDomain
     * @param list<string> $reportedUri
     */
    public function __construct(
        public FeedbackType $type,
        public ?string $userAgent = null,
        public ?string $version = null,
        public ?string $originalMailFrom = null,
        public array $originalRcptTo = [],
        public ?string $arrivalDate = null,
        public ?string $reportingMta = null,
        public ?string $sourceIp = null,
        public int $incidents = 1,
        public array $reportedDomain = [],
        public array $reportedUri = [],
        public ?string $authenticationResults = null,
    ) {
    }
}
