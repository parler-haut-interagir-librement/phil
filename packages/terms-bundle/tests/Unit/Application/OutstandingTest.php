<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\TermsBundle\Application\Handler\SendTermsRemindersHandler;
use Phil\TermsBundle\Application\Outstanding;
use Phil\TermsBundle\Domain\AcceptanceChannel;
use Phil\TermsBundle\Domain\Exception\InvalidTerms;
use Phil\TermsBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Outstanding::class)]
#[CoversClass(SendTermsRemindersHandler::class)]
final class OutstandingTest extends TestCase
{
    public function testTheMembersWhoAreNotInOrderNow(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);
        $kit->terms->publish('privacy', ['en' => 'one']);
        $kit->terms->accept('a', 'terms', 1, AcceptanceChannel::Form);
        $kit->terms->accept('b', 'privacy', 1, AcceptanceChannel::Form);

        $outstanding = $kit->outstanding(['a', 'b', 'c']);

        self::assertSame(['b', 'c'], [...$outstanding->at('terms', $kit->clock->now())]);
        self::assertSame(['a', 'c'], [...$outstanding->at('privacy', $kit->clock->now())]);
        self::assertSame(['a', 'b', 'c'], [...$outstanding->at('privacy', new DateTimeImmutable('2026-06-01'))]);
        self::assertSame([], [...$outstanding->at('terms', new DateTimeImmutable('2026-04-01'))]);
    }

    public function testTheMembersWhoHaveNotAcceptedTheLatestMaterialVersion(): void
    {
        $kit = new Kit();
        $kit->terms->publish('privacy', ['en' => 'one']);
        $kit->terms->accept('a', 'privacy', 1, AcceptanceChannel::Form);
        $kit->terms->publish('privacy', ['en' => 'two'], effectiveAt: new DateTimeImmutable('2026-06-01'));
        $kit->terms->publish('privacy', ['en' => 'three'], effectiveAt: new DateTimeImmutable('2026-06-01'), material: false);
        $kit->terms->accept('b', 'privacy', 2, AcceptanceChannel::Form);
        $kit->later('+60 days');

        $outstanding = $kit->outstanding(['a', 'b']);

        self::assertSame(['a'], [...$outstanding->ofLatestMaterial('privacy')]);
        self::assertSame(['a', 'b'], [...$outstanding->at('privacy', $kit->clock->now())]);
        self::assertSame([], [...$outstanding->ofLatestMaterial('terms')]);
    }

    public function testTheMembersAreAskedForInBatchesOf500(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);
        $kit->terms->accept('m2', 'terms', 1, AcceptanceChannel::Form);
        $kit->terms->accept('m1000', 'terms', 1, AcceptanceChannel::Form);

        $members = [...$kit->outstanding(numbered: 1001)->at('terms', $kit->clock->now())];

        self::assertCount(999, $members);
        self::assertSame(['m1', 'm3'], \array_slice($members, 0, 2));
        self::assertSame('m1001', $members[998]);
        self::assertSame([500, 500, 1], array_map(count(...), $kit->acceptances->batches));

        $keys = [];

        foreach ($kit->outstanding(numbered: 3)->at('terms', $kit->clock->now()) as $key => $member) {
            $keys[$key] = $member;
        }

        self::assertSame([0 => 'm1', 1 => 'm3'], $keys);
    }

    public function testExactly500MembersMakeOneBatch(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);

        self::assertCount(500, [...$kit->outstanding(numbered: 500)->at('terms', $kit->clock->now())]);
        self::assertSame([500], array_map(count(...), $kit->acceptances->batches));
    }

    public function testAnUndeclaredPolicyIsRefusedAtTheCall(): void
    {
        $kit = new Kit();

        $this->expectException(InvalidTerms::class);

        $kit->outstanding()->at('cookies', $kit->clock->now());
    }

    public function testAnUndeclaredPolicyIsRefusedForTheLatestVersion(): void
    {
        $this->expectException(InvalidTerms::class);

        new Kit()->outstanding()->ofLatestMaterial('cookies');
    }

    public function testTheRemindersGoToTheMembersWhoWillNotBeInOrder(): void
    {
        $kit = new Kit();
        $kit->terms->publish('terms', ['en' => 'one']);
        $kit->terms->publish('privacy', ['en' => 'one']);
        $kit->terms->publish('conduct', ['en' => 'one']);
        $kit->terms->accept('a', 'terms', 1, AcceptanceChannel::Form);
        $kit->terms->accept('a', 'privacy', 1, AcceptanceChannel::Form);
        $kit->later('+20 days');
        $kit->terms->accept('b', 'privacy', 1, AcceptanceChannel::Form);
        $kit->terms->publish('terms', ['en' => 'two'], effectiveAt: new DateTimeImmutable('2026-05-30'));

        new SendTermsRemindersHandler($kit->policies, $kit->catalog, $kit->outstanding(['a', 'b']), $kit->notifier, $kit->clock, 14)();

        self::assertSame([['terms', 2, ['a', 'b']], ['privacy', 1, ['a']]], $kit->notifier->reminders);
    }

    public function testTheRemindersGoInBatchesOf500AndSkipAPolicyWithoutVersion(): void
    {
        $kit = new Kit();
        $kit->terms->publish('privacy', ['en' => 'one']);

        new SendTermsRemindersHandler($kit->policies, $kit->catalog, $kit->outstanding(numbered: 1001), $kit->notifier, $kit->clock)();

        self::assertSame([500, 500, 1], array_map(static fn (array $reminder): int => \count($reminder[2]), $kit->notifier->reminders));
        self::assertSame('m1', $kit->notifier->reminders[0][2][0]);
        self::assertSame('m501', $kit->notifier->reminders[1][2][0]);
        self::assertSame(['m1001'], $kit->notifier->reminders[2][2]);
    }

    public function testExactly500MembersMakeOneReminder(): void
    {
        $kit = new Kit();
        $kit->terms->publish('privacy', ['en' => 'one']);

        new SendTermsRemindersHandler($kit->policies, $kit->catalog, $kit->outstanding(numbered: 500), $kit->notifier, $kit->clock)();

        self::assertCount(1, $kit->notifier->reminders);
    }

    public function testTheDefaultLooks14DaysAhead(): void
    {
        $kit = new Kit();
        $kit->terms->publish('privacy', ['en' => 'one']);
        $kit->terms->accept('a', 'privacy', 1, AcceptanceChannel::Form);
        $kit->later('+16 days');

        $handler = new SendTermsRemindersHandler($kit->policies, $kit->catalog, $kit->outstanding(['a']), $kit->notifier, $kit->clock);

        $handler();
        self::assertSame([], $kit->notifier->reminders);

        $kit->later('+1 second');
        $handler();
        self::assertSame([['privacy', 1, ['a']]], $kit->notifier->reminders);
    }
}
