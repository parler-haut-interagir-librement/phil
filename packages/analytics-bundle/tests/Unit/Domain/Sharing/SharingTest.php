<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Sharing;

use DateTimeImmutable;
use Phil\AnalyticsBundle\Domain\Measure\MeasureNames;
use Phil\AnalyticsBundle\Domain\Sharing\SharedView;
use Phil\AnalyticsBundle\Domain\Sharing\ShareToken;
use Phil\AnalyticsBundle\Domain\ValueObject\SiteId;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SharedView::class)]
#[CoversClass(ShareToken::class)]
final class SharingTest extends TestCase
{
    private SiteId $site;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->site = SiteId::fromString('s');
        $this->now = new DateTimeImmutable('2026-11-09T12:00:00+00:00');
    }

    public function testAGeneratedTokenReadsBackAndIsUnguessable(): void
    {
        $token = ShareToken::generate();

        self::assertSame(43, \strlen($token->value));
        self::assertSame($token->value, ShareToken::fromString($token->value)->value);
        self::assertNotSame($token->value, ShareToken::generate()->value);
    }

    public function testOnlyTheDigestIsStored(): void
    {
        $token = ShareToken::fromString(str_repeat('a', 43));

        self::assertSame(hash('sha256', str_repeat('a', 43)), $token->digest());
        self::assertSame($token->digest(), SharedView::link($this->site, $token, $this->now)->tokenDigest);
    }

    public function testATokenIsNotDumped(): void
    {
        self::assertSame(['value' => '[redacted]'], ShareToken::generate()->__debugInfo());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAToken(): iterable
    {
        yield 'short' => [str_repeat('a', 42)];
        yield 'long' => [str_repeat('a', 44)];
        yield 'a symbol' => [str_repeat('a', 42).'/'];
        yield 'a trailing newline' => [str_repeat('a', 43)."\n"];
        yield 'text before' => ['/'.str_repeat('a', 43)];
    }

    #[DataProvider('notAToken')]
    public function testWhatIsNotATokenIsRefused(string $value): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('This is not a share token.');

        ShareToken::fromString($value);
    }

    public function testAPublicBoardHasNoTokenAndStaysOpenUntilRevoked(): void
    {
        $board = SharedView::publicBoard($this->site, $this->now);

        self::assertNull($board->tokenDigest);
        self::assertSame($this->site, $board->site);
        self::assertSame($this->now, $board->createdAt);
        self::assertTrue($board->isOpenAt($this->now->modify('+10 years')));

        $revoked = $board->revoked($this->now);

        self::assertSame($this->now, $revoked->revokedAt);
        self::assertSame($this->site, $revoked->site);
        self::assertSame($this->now, $revoked->createdAt);
        self::assertFalse($revoked->isOpenAt($this->now));
    }

    public function testALinkExpires(): void
    {
        $expiry = $this->now->modify('+1 day');
        $link = SharedView::link($this->site, ShareToken::generate(), $this->now, $expiry);

        self::assertSame($expiry, $link->expiresAt);
        self::assertTrue($link->isOpenAt($expiry->modify('-1 second')));
        self::assertFalse($link->isOpenAt($expiry));
    }

    public function testRevokingKeepsTheFirstRevocationAndTheRest(): void
    {
        $expiry = $this->now->modify('+1 day');
        $link = SharedView::link($this->site, ShareToken::generate(), $this->now, $expiry);
        $first = $link->revoked($this->now);
        $second = $first->revoked($this->now->modify('+1 hour'));

        self::assertSame($this->now, $second->revokedAt);
        self::assertSame($link->tokenDigest, $second->tokenDigest);
        self::assertSame($expiry, $second->expiresAt);
    }

    public function testAViewCannotExpireBeforeItExists(): void
    {
        self::assertTrue(SharedView::link($this->site, ShareToken::generate(), $this->now, $this->now->modify('+1 second'))->isOpenAt($this->now));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A shared view expires after it is created.');

        SharedView::link($this->site, ShareToken::generate(), $this->now, $this->now);
    }

    public function testASharedViewNeverHoldsThePrivateMetrics(): void
    {
        $view = SharedView::publicBoard($this->site, $this->now);

        self::assertTrue($view->mayShow(MeasureNames::PAGEVIEWS));
        self::assertTrue($view->mayShow(MeasureNames::OUTBOUND));
        self::assertFalse($view->mayShow(MeasureNames::OUTBOUND_LINK));
        self::assertFalse($view->mayShow(MeasureNames::SEARCH));
    }
}
