<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\LtiBundle\Application\PendingScore;
use Phil\LtiBundle\Application\StoredDeepLinkingReturn;
use Phil\LtiBundle\Infrastructure\Memory\InMemoryDeepLinkingReturns;
use Phil\LtiBundle\Infrastructure\Memory\InMemoryPendingScores;
use Phil\LtiBundle\Tests\Unit\Fixture\Contracts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryPendingScores::class)]
#[CoversClass(InMemoryDeepLinkingReturns::class)]
#[CoversClass(PendingScore::class)]
#[CoversClass(StoredDeepLinkingReturn::class)]
final class MemoryStoresTest extends TestCase
{
    use Contracts;

    public function testThePendingScoresKeepTheirStateAndAreClaimedOnce(): void
    {
        self::checkPendingScores(new InMemoryPendingScores());
    }

    public function testTheDeepLinkingReturnsAreKeptUntilTheyExpire(): void
    {
        self::checkDeepLinkingReturns(new InMemoryDeepLinkingReturns());
    }

    public function testAResetForgetsTheClaimsAndTheReturns(): void
    {
        $now = new DateTimeImmutable('2026-11-14 12:00:00 UTC');
        $returns = new InMemoryDeepLinkingReturns();
        $returns->save(new StoredDeepLinkingReturn('k', 'c', [], $now, $now->modify('+1 hour')));
        $returns->reset();
        self::assertNull($returns->find('k', $now));

        $pending = new InMemoryPendingScores();
        self::checkPendingScores($pending);
        $pending->reset();
        self::assertNull($pending->find('p6'));
        self::checkPendingScores($pending);
    }
}
