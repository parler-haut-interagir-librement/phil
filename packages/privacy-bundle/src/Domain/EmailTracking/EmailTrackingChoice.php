<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\EmailTracking;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * What a member chose about the emails sent to them: whether their opens may be counted, by a pixel, and whether their
 * clicks may be followed, by redirected links. Each one is asked on its own, and both start refused.
 *
 * **The version is part of the consent.** A member agreed to a text; when the application changes the version of that
 * text, the earlier choice stops counting and nothing is followed until the member chooses again. A choice that refuses
 * both is kept too: it is the dated proof of the withdrawal.
 */
final readonly class EmailTrackingChoice
{
    public function __construct(
        public DataSubject $subject,
        public string $version,
        public bool $opens,
        public bool $clicks,
        public DateTimeImmutable $decidedAt,
    ) {
        if (1 !== preg_match(ConsentPolicy::VERSION_PATTERN, $version)) {
            throw InvalidPrivacyValue::because('This is not a version of the text about tracked emails.');
        }
    }

    /**
     * Whether the opens may be counted under this version of the text.
     */
    public function allowsOpens(string $version): bool
    {
        return $this->opens && $this->version === $version;
    }

    /**
     * Whether the clicks may be followed under this version of the text.
     */
    public function allowsClicks(string $version): bool
    {
        return $this->clicks && $this->version === $version;
    }

    /**
     * The choice as the member's export shows it, its date in UTC.
     *
     * @return array{version: string, opens: bool, clicks: bool, decided_at: string}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'opens' => $this->opens,
            'clicks' => $this->clicks,
            'decided_at' => $this->decidedAt->setTimezone(new DateTimeZone('UTC'))->format(DateTimeImmutable::ATOM),
        ];
    }
}
