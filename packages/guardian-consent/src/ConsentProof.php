<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use DateTimeImmutable;
use DateTimeZone;
use Phil\GuardianConsent\Exception\InvalidConsent;
use Phil\GuardianConsent\Internal\RandomId;

/**
 * The dated record of one decision on one feature: who decided — the guardian by their identifier, or the member —,
 * what, when, on which version of the text, and by which request. Proofs are only ever added: a later one supersedes
 * an earlier one for the same feature.
 */
final readonly class ConsentProof
{
    public const int ID_BYTES = 16;

    public DateTimeImmutable $recordedAt;

    /**
     * @param ?GuardianId $guardian the guardian who decided; none for the member's own decisions, and none once the
     *                              guardian is forgotten
     *
     * @throws InvalidConsent when the identifier is not one the library issues, a member's decision names a guardian, or
     *                        a request identifier is not one the library issues
     */
    public function __construct(
        public string $id,
        public MemberId $member,
        public Feature $feature,
        public Decision $decision,
        public Actor $actor,
        public ?GuardianId $guardian,
        public ?NoticeVersion $notice,
        public ?string $requestId,
        DateTimeImmutable $recordedAt,
    ) {
        if (!RandomId::isValid($id, self::ID_BYTES)) {
            throw InvalidConsent::because('A proof is named by an identifier the library issues.');
        }

        if (Actor::Member === $actor && $guardian instanceof GuardianId) {
            throw InvalidConsent::because('A decision of the member names no guardian.');
        }

        if (null !== $requestId && !ConfirmationToken::isRequestId($requestId)) {
            throw InvalidConsent::because('A proof names a request by an identifier the library issues.');
        }

        $this->recordedAt = $recordedAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The same proof, the guardian no longer named.
     */
    public function withoutGuardian(): self
    {
        return new self($this->id, $this->member, $this->feature, $this->decision, $this->actor, null, $this->notice, $this->requestId, $this->recordedAt);
    }
}
