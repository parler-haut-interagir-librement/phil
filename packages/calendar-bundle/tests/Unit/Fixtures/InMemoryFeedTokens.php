<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Fixtures;

use Override;
use Phil\CalendarBundle\Domain\Model\PersonalFeedToken;
use Phil\CalendarBundle\Domain\Repository\PersonalFeedTokenRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

final class InMemoryFeedTokens implements PersonalFeedTokenRepository
{
    /** @var array<string, PersonalFeedToken> by member */
    public array $tokens = [];

    /** How many times a token was looked up by its digest. */
    public int $lookups = 0;

    #[Override]
    public function replace(PersonalFeedToken $token): void
    {
        $this->tokens[$token->member()->toString()] = $token;
    }

    #[Override]
    public function ofMember(Member $member): ?PersonalFeedToken
    {
        return $this->tokens[$member->toString()] ?? null;
    }

    #[Override]
    public function byDigest(string $digest): ?PersonalFeedToken
    {
        ++$this->lookups;

        return array_find($this->tokens, static fn (PersonalFeedToken $token): bool => $token->digest === $digest);
    }

    #[Override]
    public function forgetMember(Member $member): void
    {
        unset($this->tokens[$member->toString()]);
    }
}
