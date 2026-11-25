<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\FaqBundle\Application\Exception\VoteRefused;
use Phil\FaqBundle\Application\VoteTicket;
use Phil\FaqBundle\Application\VoteTokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(VoteTokens::class)]
#[CoversClass(VoteTicket::class)]
#[CoversClass(VoteRefused::class)]
final class VoteTokensTest extends TestCase
{
    private const string SECRET = 'a secret long enough for a test';

    public function testATokenIsReadBackUntilItExpires(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00 UTC');
        $tokens = new VoteTokens(self::SECRET, $clock, 3_600);
        $token = $tokens->issue();

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22}\.\d+\.[A-Za-z0-9_-]{43}$/D', $token);
        self::assertSame((string) ($clock->now()->getTimestamp() + 3_600), explode('.', $token)[1]);

        $clock->sleep(3_599);
        $ticket = $tokens->read($token);
        self::assertSame([explode('.', $token)[0], $clock->now()->getTimestamp() + 1], [$ticket->nonce, $ticket->expiresAt->getTimestamp()]);

        $clock->sleep(1);
        $this->expectExceptionObject(VoteRefused::expiredToken());

        $tokens->read($token);
    }

    public function testTheDefaultLifetimeIsADay(): void
    {
        $clock = new MockClock('2026-11-21 10:00:00 UTC');

        self::assertSame((string) ($clock->now()->getTimestamp() + 86_400), explode('.', new VoteTokens(self::SECRET, $clock)->issue())[1]);
    }

    public function testEveryTokenIsAnother(): void
    {
        $tokens = new VoteTokens(self::SECRET, new MockClock());

        self::assertNotSame($tokens->issue(), $tokens->issue());
    }

    public function testATokenOfAnotherSecretIsRefused(): void
    {
        $clock = new MockClock();
        $token = new VoteTokens('another secret', $clock)->issue();

        $this->expectExceptionObject(VoteRefused::invalidToken());

        new VoteTokens(self::SECRET, $clock)->read($token);
    }

    public function testAChangedExpiryIsRefused(): void
    {
        $tokens = new VoteTokens(self::SECRET, new MockClock());
        [$nonce, $expiry, $mac] = explode('.', $tokens->issue());

        $this->expectExceptionObject(VoteRefused::invalidToken());

        $tokens->read($nonce.'.'.((int) $expiry + 1).'.'.$mac);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformed(): iterable
    {
        yield 'empty' => [''];
        yield 'two parts' => ['abc.123'];
        yield 'a short nonce' => [str_repeat('a', 21).'.1.'.str_repeat('a', 43)];
        yield 'a zero expiry' => [str_repeat('a', 22).'.0.'.str_repeat('a', 43)];
        yield 'a long signature' => [str_repeat('a', 22).'.1.'.str_repeat('a', 44)];
        yield 'a trailing line' => [str_repeat('a', 22).'.1.'.str_repeat('a', 43)."\n"];
        yield 'too long' => [str_repeat('a', 129)];
    }

    #[DataProvider('malformed')]
    public function testAMalformedTokenIsRefused(string $token): void
    {
        $this->expectExceptionObject(VoteRefused::invalidToken());

        new VoteTokens(self::SECRET, new MockClock())->read($token);
    }

    public function testTheLongestTokenIsRead(): void
    {
        $clock = new MockClock('@1000');
        $tokens = new VoteTokens(self::SECRET, $clock);
        $token = $tokens->issue();

        self::assertLessThanOrEqual(VoteTokens::MAX_LENGTH, \strlen($token));
        self::assertSame(87_400, $tokens->read($token)->expiresAt->getTimestamp());
    }

    public function testATicketVotesUnderAnotherNameForEachQuestion(): void
    {
        $ticket = new VoteTicket('nonce', new DateTimeImmutable('@1'));

        self::assertSame(hash('sha256', "nonce\0q1"), $ticket->spentId('q1'));
        self::assertNotSame($ticket->spentId('q1'), $ticket->spentId('q2'));
    }

    public function testTheRefusalsSayWhy(): void
    {
        self::assertSame('The request did not pass the CSRF check.', VoteRefused::untrustedRequest()->getMessage());
        self::assertSame('The vote token has expired: the page is to be loaded again.', VoteRefused::expiredToken()->getMessage());
        self::assertSame('The vote token is not valid.', VoteRefused::invalidToken()->getMessage());
    }

    public function testATokenIsSignedOverItsContextAndItsPayload(): void
    {
        $payload = str_repeat('A', 22).'.2000';
        $mac = rtrim(strtr(base64_encode(hash_hmac('sha256', 'phil_faq_vote|'.$payload, hash_hkdf('sha256', self::SECRET, 32, 'phil_faq.vote_token'), true)), '+/', '-_'), '=');

        self::assertSame(2000, new VoteTokens(self::SECRET, new MockClock('@1000'))->read($payload.'.'.$mac)->expiresAt->getTimestamp());
    }

    public function testATokenWithTwelveDigitsIsTheLongestRead(): void
    {
        $clock = new MockClock('@99999999999');
        $tokens = new VoteTokens(self::SECRET, $clock, 3_600);
        $token = $tokens->issue();

        self::assertSame(VoteTokens::MAX_LENGTH, \strlen($token));
        self::assertSame(100_000_003_599, $tokens->read($token)->expiresAt->getTimestamp());
    }
}
