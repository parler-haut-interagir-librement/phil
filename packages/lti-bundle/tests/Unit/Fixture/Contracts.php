<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\Lti\Service\Ags\ActivityProgress;
use Phil\Lti\Service\Ags\GradingProgress;
use Phil\Lti\Service\Ags\Score;
use Phil\LtiBundle\Application\Contract\DeepLinkingReturns;
use Phil\LtiBundle\Application\Contract\PendingScores;
use Phil\LtiBundle\Application\PendingScore;
use Phil\LtiBundle\Application\StoredDeepLinkingReturn;
use Phil\Url\Url;
use PHPUnit\Framework\Assert;

/**
 * The contracts of the stores the bundle defines, checked the same way on memory and on each database.
 */
trait Contracts
{
    private static function checkPendingScores(PendingScores $pending): void
    {
        $at = static fn (string $time): DateTimeImmutable => new DateTimeImmutable('2026-11-14 '.$time.' UTC');
        $make = static fn (string $id, string $created): PendingScore => new PendingScore($id, 'https://lms.example.org', 'client', Url::fromString('https://lms.example.org/items/'.$id), new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00+00:00'), 1.0, 2.0), $at($created));
        $ids = static fn (array $scores): array => array_map(static fn (PendingScore $score): string => $score->id, $scores);

        $pending->save($make('p2', '11:30:00'));
        $pending->save($make('p1', '11:00:00'));
        $pending->save($make('p0', '11:00:00'));
        $pending->save($make('p3', '11:59:30'));
        $pending->save($make('p4', '10:00:00'));
        $pending->save($make('p5', '10:00:00'));
        $pending->markRefused('p4', 'Refused for good: '.str_repeat('é', 600), $at('11:00:00'));
        $pending->markDispatched('p5', $at('10:00:01'));
        $pending->markDispatched('nope', $at('10:00:01'));
        $pending->markRefused('nope', 'x', $at('10:00:01'));
        Assert::assertNull($pending->find('nope'));

        $refused = $pending->find('p4');
        Assert::assertNotNull($refused);
        Assert::assertEquals($at('11:00:00'), $refused->refusedAt);
        Assert::assertSame(PendingScore::MAX_REFUSAL, \strlen($refused->refusal ?? ''));
        Assert::assertTrue(mb_check_encoding($refused->refusal ?? '', 'UTF-8'));
        Assert::assertStringStartsWith('Refused for good: é', $refused->refusal ?? '');
        Assert::assertNull($refused->dispatchedAt);
        Assert::assertEquals($at('10:00:01'), $pending->find('p5')?->dispatchedAt);
        Assert::assertNull($pending->find('p5')?->refusedAt);

        Assert::assertSame(['p0', 'p1', 'p2'], $ids($pending->claim($at('11:59:00'), $at('12:00:00'), 10)));
        Assert::assertSame([], $pending->claim($at('11:59:00'), $at('12:00:01'), 10), 'A score claimed is not claimed again before the delay.');
        Assert::assertSame(['p0'], $ids($pending->claim($at('12:00:00'), $at('12:00:05'), 1)));
        Assert::assertSame(['p1'], $ids($pending->claim($at('12:00:00'), $at('12:00:05'), 1)));
        $pending->markDispatched('p2', $at('12:00:06'));
        Assert::assertSame(['p0', 'p1', 'p3'], $ids($pending->claim($at('12:10:00'), $at('12:11:00'), 10)));

        try {
            $pending->claim($at('12:00:00'), $at('12:00:00'), 10);
            Assert::fail('A claim at its threshold was made.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            Assert::assertStringContainsString('after its threshold', $invalidArgumentException->getMessage());
        }

        $pending->save($make('p6', '09:00:00'));
        $pending->markRefused('p6', 'Later', $at('11:30:00'));
        Assert::assertSame([], $pending->refused($at('10:59:59'), 10));
        Assert::assertSame(['p4'], $ids($pending->refused($at('11:00:00'), 10)));
        Assert::assertSame(['p4'], $ids($pending->refused($at('12:00:00'), 1)));
        Assert::assertSame(['p4', 'p6'], $ids($pending->refused($at('12:00:00'), 10)));
        Assert::assertSame(['p0', 'p1', 'p3'], $ids($pending->claim($at('12:30:00'), $at('12:31:00'), 10)), 'A refused or dispatched score is never claimed.');

        $pending->delete('p4');
        Assert::assertNull($pending->find('p4'));
        Assert::assertSame(['p6'], $ids($pending->refused($at('12:00:00'), 10)));
    }

    private static function checkDeepLinkingReturns(DeepLinkingReturns $returns): void
    {
        $at = static fn (string $time): DateTimeImmutable => new DateTimeImmutable('2026-11-14 '.$time.' UTC');
        $keys = static fn (array $kept): array => array_map(static fn (StoredDeepLinkingReturn $return): string => $return->key, $kept);
        $claims = ['iss' => 'client', 'nonce' => 'n', 'https://purl.imsglobal.org/spec/lti-dl/claim/content_items' => [['type' => 'link', 'url' => 'https://example.com/é']]];

        $returns->save(new StoredDeepLinkingReturn('b', 'client', $claims, $at('12:00:00'), $at('13:00:00')));
        $returns->save(new StoredDeepLinkingReturn('b', 'other', [], $at('12:30:00'), $at('14:00:00')));
        $returns->save(new StoredDeepLinkingReturn('a', 'client', [], $at('12:00:00'), $at('12:10:00')));
        $returns->save(new StoredDeepLinkingReturn('c', 'client', [], $at('11:00:00'), $at('13:00:00'), $at('11:00:01')));
        $returns->save(new StoredDeepLinkingReturn('d', 'client', [], $at('12:05:00'), $at('13:00:00')));

        Assert::assertEquals(new StoredDeepLinkingReturn('b', 'client', $claims, $at('12:00:00'), $at('13:00:00')), $returns->find('b', $at('12:59:59')));
        Assert::assertNull($returns->find('b', $at('13:00:00')));
        Assert::assertNull($returns->find('nope', $at('12:00:00')));
        Assert::assertEquals($at('11:00:01'), $returns->find('c', $at('12:00:00'))?->handledAt);

        Assert::assertSame(['a', 'b', 'd'], $keys($returns->unhandled($at('12:05:00'), $at('12:06:00'), 10)));
        Assert::assertSame(['a', 'b'], $keys($returns->unhandled($at('12:04:59'), $at('12:06:00'), 10)));
        Assert::assertSame(['a'], $keys($returns->unhandled($at('12:05:00'), $at('12:06:00'), 1)));
        Assert::assertSame(['b', 'd'], $keys($returns->unhandled($at('12:05:00'), $at('12:10:00'), 10)));

        $returns->markHandled('b', $at('12:20:00'));
        $returns->markHandled('nope', $at('12:20:00'));
        Assert::assertEquals($at('12:20:00'), $returns->find('b', $at('12:20:00'))?->handledAt);
        Assert::assertSame(['d'], $keys($returns->unhandled($at('12:30:00'), $at('12:30:00'), 10)));

        Assert::assertSame(0, $returns->purge($at('12:09:59')));
        Assert::assertSame(1, $returns->purge($at('12:10:00')));
        Assert::assertSame(3, $returns->purge($at('13:00:00')));
        Assert::assertSame(0, $returns->purge($at('13:00:00')));
    }
}
