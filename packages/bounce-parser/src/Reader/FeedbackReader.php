<?php

declare(strict_types=1);

namespace Phil\BounceParser\Reader;

use Phil\BounceParser\FeedbackReport;
use Phil\BounceParser\FeedbackType;
use Phil\BounceParser\Mime\Headers;
use Phil\BounceParser\Mime\Part;

/**
 * Reads the `message/feedback-report` part of an abuse report (RFC 5965).
 *
 * @internal
 */
final readonly class FeedbackReader
{
    public function __construct(
        private int $maxHeaders,
    ) {
    }

    public function read(Part $message): ?FeedbackReport
    {
        $part = $message->find('message/feedback-report');

        if (!$part instanceof Part) {
            return null;
        }

        $fields = Headers::parse($part->body, $this->maxHeaders);
        $incidents = $fields->get('Incidents');

        return new FeedbackReport(
            FeedbackType::read($fields->get('Feedback-Type')),
            $fields->get('User-Agent'),
            $fields->get('Version'),
            Addresses::ofHeader($fields->get('Original-Mail-From')),
            array_values(array_filter(array_map(Addresses::ofHeader(...), $fields->all('Original-Rcpt-To')), static fn (?string $address): bool => null !== $address)),
            $fields->get('Arrival-Date') ?? $fields->get('Received-Date'),
            $this->afterType($fields->get('Reporting-MTA')),
            $fields->get('Source-IP'),
            null !== $incidents && ctype_digit($incidents) && \strlen($incidents) < 10 ? max(1, (int) $incidents) : 1,
            $fields->all('Reported-Domain'),
            $fields->all('Reported-URI'),
            $fields->get('Authentication-Results'),
        );
    }

    private function afterType(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $semicolon = strpos($value, ';');

        return trim(false === $semicolon ? $value : substr($value, $semicolon + 1));
    }
}
