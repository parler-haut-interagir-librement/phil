<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Domain\Model\PersonalFeedToken;
use Phil\CalendarBundle\Domain\Repository\PersonalFeedTokenRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Psr\Clock\ClockInterface;

/**
 * The secrets of members' own iCalendar feeds: 32 random bytes, written in 64 hexadecimal digits, the only key to
 * the feed's address. Only their SHA-256 digest is kept; a new secret revokes the one before, and a revoked one answers
 * no more.
 */
final readonly class PersonalFeeds
{
    /** The pattern of a secret, checked before any digest is looked up. */
    public const string PATTERN = '[0-9a-f]{64}';

    public function __construct(
        private PersonalFeedTokenRepository $tokens,
        private ClockInterface $clock,
    ) {
    }

    /**
     * A new secret for the member, in place of the one they had: shown once, to make the feed's address.
     */
    public function issue(Member $member): string
    {
        $secret = sodium_bin2hex(random_bytes(32));
        $this->tokens->replace(new PersonalFeedToken($member, $this->digest($secret), DateTimeImmutable::createFromInterface($this->clock->now())->setTimezone(new DateTimeZone('UTC'))));

        return $secret;
    }

    /**
     * The member's feed answers no more, until a new secret is issued.
     */
    public function revoke(Member $member): void
    {
        $this->tokens->forgetMember($member);
    }

    /**
     * Whose feed a secret opens, or null for one malformed, unknown or revoked.
     */
    public function memberOf(string $secret): ?Member
    {
        if (1 !== preg_match('/^'.self::PATTERN.'$/D', $secret)) {
            return null;
        }

        return $this->tokens->byDigest($this->digest($secret))?->member();
    }

    /**
     * When the member's secret was issued, in UTC; null when they have none.
     */
    public function issuedAt(Member $member): ?DateTimeImmutable
    {
        return $this->tokens->ofMember($member)?->issuedAt;
    }

    private function digest(string $secret): string
    {
        return hash('sha256', $secret);
    }
}
