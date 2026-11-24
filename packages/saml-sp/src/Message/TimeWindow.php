<?php

declare(strict_types=1);

namespace Phil\SamlSp\Message;

use DateInterval;
use DateTimeImmutable;
use Phil\SamlSp\Exception\InvalidMessage;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSp\Xml\Instant;

/**
 * The instant a message is read at, widened by the clock skew of the policy: a condition starting a little in the
 * future, or ending a little in the past, still holds.
 */
final readonly class TimeWindow
{
    private DateTimeImmutable $early;

    private DateTimeImmutable $late;

    private DateTimeImmutable $oldest;

    public function __construct(public DateTimeImmutable $now, private int $skew, int $maxAge)
    {
        $this->early = $now->sub(new DateInterval('PT'.$skew.'S'));
        $this->late = $now->add(new DateInterval('PT'.$skew.'S'));
        $this->oldest = $this->early->sub(new DateInterval('PT'.$maxAge.'S'));
    }

    public static function of(DateTimeImmutable $now, SecurityPolicy $policy): self
    {
        return new self($now, $policy->clockSkew, $policy->maxAge);
    }

    /**
     * Refuses an issue instant in the future, or older than the age the policy allows.
     */
    public function checkIssued(?string $issueInstant, string $what): void
    {
        $instant = Instant::parse((string) $issueInstant, $what.' issue instant');

        if ($instant > $this->late || $instant < $this->oldest) {
            throw InvalidMessage::because(\sprintf('%s was issued outside the time the service provider accepts.', $what));
        }
    }

    /**
     * Refuses a `NotBefore` still in the future; an absent one holds.
     */
    public function checkNotBefore(?string $notBefore, string $what): void
    {
        if (null !== $notBefore && Instant::parse($notBefore, $what) > $this->late) {
            throw InvalidMessage::because(\sprintf('%s is not valid yet.', $what));
        }
    }

    /**
     * Refuses a `NotOnOrAfter` already past, and returns it widened by the skew: until when the message is remembered.
     */
    public function checkNotOnOrAfter(string $notOnOrAfter, string $what): DateTimeImmutable
    {
        $instant = Instant::parse($notOnOrAfter, $what);

        if ($this->early >= $instant) {
            throw InvalidMessage::because(\sprintf('%s has expired.', $what));
        }

        return $instant->add(new DateInterval('PT'.$this->skew.'S'));
    }
}
