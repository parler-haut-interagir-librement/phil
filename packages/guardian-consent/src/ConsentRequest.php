<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use DateTimeImmutable;
use DateTimeZone;
use Phil\GuardianConsent\Exception\InvalidConsent;

/**
 * A request sent to a guardian: the member, the features asked, the version of the text shown, when it was sent and
 * until when it may be answered, and the keyed hash of its confirmation token, never the token itself.
 *
 * The guardian's address stays here, and only here, until the request is answered or erased.
 */
final readonly class ConsentRequest
{
    public const int MAX_FEATURES = 64;

    /** @var non-empty-list<Feature> */
    public array $features;

    public DateTimeImmutable $requestedAt;

    public DateTimeImmutable $expiresAt;

    /**
     * @param list<Feature> $features
     *
     * @throws InvalidConsent when the identifier is not one the library issues, the features are none, more than 64
     *                        or given twice, or the request expires before it is sent
     */
    public function __construct(
        public string $id,
        public MemberId $member,
        public Guardian $guardian,
        array $features,
        public NoticeVersion $notice,
        DateTimeImmutable $requestedAt,
        DateTimeImmutable $expiresAt,
        public string $tokenHash,
    ) {
        if (!ConfirmationToken::isRequestId($id)) {
            throw InvalidConsent::because('A request is named by an identifier the library issues.');
        }

        $this->features = $this->checkFeatures($features);

        if ($expiresAt <= $requestedAt) {
            throw InvalidConsent::because('A request expires after it is sent.');
        }

        $utc = new DateTimeZone('UTC');
        $this->requestedAt = $requestedAt->setTimezone($utc);
        $this->expiresAt = $expiresAt->setTimezone($utc);
    }

    /**
     * @param list<Feature> $features
     *
     * @return non-empty-list<Feature>
     *
     * @throws InvalidConsent when the features are none, more than 64 or given twice
     */
    private function checkFeatures(array $features): array
    {
        if ([] === $features || \count($features) > self::MAX_FEATURES) {
            throw InvalidConsent::because(\sprintf('A request asks 1 to %d features.', self::MAX_FEATURES));
        }

        // A feature is read as its name.
        if (\count(array_unique($features, \SORT_STRING)) !== \count($features)) {
            throw InvalidConsent::because('A feature is asked once.');
        }

        return $features;
    }

    public function covers(Feature $feature): bool
    {
        return array_any($this->features, static fn (Feature $asked): bool => $asked->equals($feature));
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }
}
