<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\EmailAddressBundle\Application\Bounces\BounceRules;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use Phil\EmailAddressBundle\Domain\ValueObject\AddressFingerprint;
use Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice;
use Phil\EmailAddressBundle\Infrastructure\InMemory\InMemoryBounceHistoryRepository;
use Phil\EmailAddressBundle\Tests\Unit\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(BounceRules::class)]
#[CoversClass(BounceDecided::class)]
final class BounceRulesTest extends TestCase
{
    private MockClock $clock;

    private RecordingDispatcher $dispatcher;

    private InMemoryBounceHistoryRepository $history;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-20 10:00:00');
        $this->dispatcher = new RecordingDispatcher();
        $this->history = new InMemoryBounceHistoryRepository($this->clock);
    }

    public function testAHardBounceSuspendsTheAddress(): void
    {
        $notice = $this->notice(NoticeKind::Hard);

        self::assertSame(BounceDecision::Suspend, $this->rules()->apply($notice));
        self::assertEquals([new BounceDecided($notice, BounceDecision::Suspend, 0)], $this->dispatcher->events);
    }

    public function testSoftBouncesAreCountedUpToTheThreshold(): void
    {
        $rules = $this->rules(threshold: 3);

        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r1')));
        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r2')));
        self::assertSame(BounceDecision::Suspend, $rules->apply($this->notice(NoticeKind::Soft, 'r3')));
        self::assertSame([1, 2, 3], array_map(static fn (object $event): int => $event instanceof BounceDecided ? $event->softBounces : -1, $this->dispatcher->events));

        // A suspension forgets the count: the address starts again from nothing.
        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r4')));
    }

    public function testAThresholdOfOneSuspendsAtTheFirstSoftBounce(): void
    {
        self::assertSame(BounceDecision::Suspend, $this->rules(threshold: 1)->apply($this->notice(NoticeKind::Soft)));
    }

    public function testOnlyTheSoftBouncesWithinTheWindowCount(): void
    {
        $rules = $this->rules(threshold: 2, window: 'P7D');

        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r1')));
        $this->clock->modify('+7 days +1 second');
        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r2')));
        $this->clock->modify('+7 days');
        self::assertSame(BounceDecision::Suspend, $rules->apply($this->notice(NoticeKind::Soft, 'r3')));
    }

    public function testTheSoftBouncesOfEachAddressAreCountedApart(): void
    {
        $rules = $this->rules(threshold: 2);

        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r1', 'ada@example.org')));
        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r2', 'grace@example.org')));
        self::assertSame(BounceDecision::Suspend, $rules->apply($this->notice(NoticeKind::Soft, 'r3', 'ada@example.org')));
    }

    public function testAHardBounceForgetsTheSoftBounces(): void
    {
        $rules = $this->rules(threshold: 2);
        $rules->apply($this->notice(NoticeKind::Soft, 'r1'));
        $rules->apply($this->notice(NoticeKind::Hard, 'r2'));

        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r3')));
    }

    public function testAnUnsubscriptionForgetsTheSoftBounces(): void
    {
        $rules = $this->rules(threshold: 2);
        $rules->apply($this->notice(NoticeKind::Soft, 'r1'));
        $rules->apply($this->notice(NoticeKind::Complaint, 'r2', authenticated: true));

        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r3')));
    }

    public function testAReviewKeepsTheSoftBounces(): void
    {
        $rules = $this->rules(threshold: 2);
        $rules->apply($this->notice(NoticeKind::Soft, 'r1'));
        $rules->apply($this->notice(NoticeKind::Complaint, 'r2'));

        self::assertSame(BounceDecision::Suspend, $rules->apply($this->notice(NoticeKind::Soft, 'r3')));
    }

    #[TestWith([NoticeKind::Complaint])]
    #[TestWith([NoticeKind::Unsubscribe])]
    public function testAnAuthenticatedComplaintUnsubscribes(NoticeKind $kind): void
    {
        self::assertSame(BounceDecision::Unsubscribe, $this->rules()->apply($this->notice($kind, authenticated: true)));
    }

    #[TestWith([NoticeKind::Complaint])]
    #[TestWith([NoticeKind::Unsubscribe])]
    public function testAComplaintWithoutAuthenticatorGoesToReviewNeverToUnsubscription(NoticeKind $kind): void
    {
        $notice = $this->notice($kind);

        self::assertSame(BounceDecision::Review, $this->rules()->apply($notice));
        self::assertEquals([new BounceDecided($notice, BounceDecision::Review)], $this->dispatcher->events);
    }

    public function testAReviewGoesToReview(): void
    {
        self::assertSame(BounceDecision::Review, $this->rules()->apply($this->notice(NoticeKind::Review, authenticated: true)));
    }

    #[TestWith([NoticeKind::Hard])]
    #[TestWith([NoticeKind::Soft])]
    #[TestWith([NoticeKind::Complaint])]
    public function testANoticeThatNamesNoAddressNorRecipientGoesToReview(NoticeKind $kind): void
    {
        $notice = new BounceNotice($kind, NoticeSource::Message, 'r1', null, null, true, $this->clock->now());

        self::assertSame(BounceDecision::Review, $this->rules()->apply($notice));
        self::assertEquals([new BounceDecided($notice, BounceDecision::Review)], $this->dispatcher->events);
    }

    public function testARecipientIdentifierIsEnoughToDecide(): void
    {
        $rules = $this->rules(threshold: 2);
        $now = $this->clock->now();
        $notice = static fn (string $report): BounceNotice => new BounceNotice(NoticeKind::Soft, NoticeSource::Mailbox, $report, null, null, false, $now, 'm-42');

        self::assertSame(BounceDecision::Count, $rules->apply($notice('r1')));
        self::assertSame(BounceDecision::Suspend, $rules->apply($notice('r2')));
    }

    public function testTheSameReportReceivedTwiceIsDecidedOnce(): void
    {
        $rules = $this->rules(threshold: 2);
        $notice = $this->notice(NoticeKind::Soft, 'r1');

        self::assertSame(
            [BounceDecision::Count, BounceDecision::Duplicate, BounceDecision::Duplicate],
            [$rules->apply($notice), $rules->apply($notice), $rules->apply($this->notice(NoticeKind::Soft, 'r1'))],
        )
        ;
        self::assertCount(1, $this->dispatcher->events);

        // The duplicate did not count: the next report is the second soft bounce.
        self::assertSame(BounceDecision::Suspend, $rules->apply($this->notice(NoticeKind::Soft, 'r2')));
    }

    public function testAClaimLastsTheWindow(): void
    {
        $rules = $this->rules(window: 'P1D');
        $notice = $this->notice(NoticeKind::Hard);
        $rules->apply($notice);

        $this->clock->modify('+23 hours');
        self::assertSame(BounceDecision::Duplicate, $rules->apply($notice));
        $this->clock->modify('+1 hour');
        self::assertSame(BounceDecision::Suspend, $rules->apply($notice));
    }

    public function testTheThresholdIsAtLeastOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The soft bounce threshold is at least 1.');

        $this->rules(threshold: 0);
    }

    #[TestWith(['30 days', 'The soft bounce window "30 days" is not an ISO 8601 duration such as P30D.'])]
    #[TestWith(['PT0S', 'The soft bounce window "PT0S" is no positive duration.'])]
    public function testTheWindowIsAPositiveDuration(string $window, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->rules(window: $window);
    }

    public function testTheRulesTellTheDefaults(): void
    {
        $rules = new BounceRules($this->history, $this->dispatcher);

        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r1')));
        self::assertSame(BounceDecision::Count, $rules->apply($this->notice(NoticeKind::Soft, 'r2')));
        self::assertSame(BounceDecision::Suspend, $rules->apply($this->notice(NoticeKind::Soft, 'r3')));
    }

    private function rules(int $threshold = 3, string $window = 'P30D'): BounceRules
    {
        return new BounceRules($this->history, $this->dispatcher, $this->clock, $threshold, $window);
    }

    private function notice(NoticeKind $kind, string $report = 'r1', string $address = 'ada@example.org', bool $authenticated = false): BounceNotice
    {
        return new BounceNotice($kind, NoticeSource::Message, $report, $address, AddressFingerprint::of($address, 'key'), $authenticated, $this->clock->now());
    }
}
