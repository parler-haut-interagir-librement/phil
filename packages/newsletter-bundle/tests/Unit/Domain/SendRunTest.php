<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Domain;

use Closure;
use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Enum\PauseReason;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\SendRun;
use Phil\NewsletterBundle\Domain\ValueObject\BreakerPolicy;
use Phil\NewsletterBundle\Domain\ValueObject\CampaignApproval;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendRun::class)]
#[CoversClass(SendRunStatus::class)]
#[CoversClass(BreakerPolicy::class)]
#[CoversClass(CampaignApproval::class)]
#[CoversClass(Campaign::class)]
final class SendRunTest extends TestCase
{
    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->at = new DateTimeImmutable('@1800000000');
    }

    public function testACampaignIsSentOnceApprovedAndAnEditWithdrawsTheApproval(): void
    {
        $campaign = new Campaign('c', Site::default(), 'l', 'Hello', 'Body', author: 'ann');

        self::assertFalse($campaign->isApproved());
        $this->refused('A campaign is sent once it is approved.', $campaign->start(...));
        self::assertSame(CampaignStatus::Draft, $campaign->status);

        $campaign->approve('bob', $this->at, true);
        self::assertTrue($campaign->isApproved());
        self::assertSame(['bob', $this->at], [$campaign->approval?->approver, $campaign->approval?->approvedAt]);

        $campaign->edit('Hello', 'Another body', '', null, null, null, ClickTracking::Aggregate);
        self::assertNull($campaign->approval);

        $campaign->approve('ann', $this->at, false);
        $campaign->schedule($this->at);
        self::assertTrue($campaign->isApproved());
        $campaign->withdrawApproval();
        self::assertFalse($campaign->isApproved());
        $campaign->approve('bob', $this->at, true);
        $campaign->start();
        self::assertSame(CampaignStatus::Sending, $campaign->status);

        $this->refused('A campaign that is sending cannot be approved.', static fn () => $campaign->approve('bob', new DateTimeImmutable(), false));
        $this->refused('A campaign that is sending cannot be withdrawn from approval.', $campaign->withdrawApproval(...));
        self::assertTrue($campaign->isApproved());
    }

    public function testTheFourEyesRuleWantsAnotherApproverThanTheAuthor(): void
    {
        $message = 'Under the four-eyes rule, a campaign is approved by someone other than its author.';
        $this->refused($message, static fn () => new Campaign('c', Site::default(), 'l', 'Hello', 'Body', author: 'ann')->approve('ann', new DateTimeImmutable(), true));
        $this->refused($message, static fn () => new Campaign('c', Site::default(), 'l', 'Hello', 'Body')->approve('ann', new DateTimeImmutable(), true));

        $campaign = new Campaign('c', Site::default(), 'l', 'Hello', 'Body');
        $campaign->approve('ann', $this->at, false);
        self::assertSame('ann', $campaign->approval?->approver);
        self::assertSame($campaign->approval, Campaign::restore(new Campaign('c', Site::default(), 'l', 'Hello', 'Body'), CampaignStatus::Draft, null, null, $campaign->approval)->approval);
    }

    public function testAnAuthorAndAnApproverAreNamesOfAtMost191Characters(): void
    {
        self::assertSame(str_repeat('é', 191), new Campaign('c', Site::default(), 'l', 'Hello', 'Body', author: str_repeat('é', 191))->author);
        self::assertSame(str_repeat('é', 191), new CampaignApproval(str_repeat('é', 191), $this->at)->approver);

        foreach (['', ' ', str_repeat('a', 192)] as $name) {
            $this->refused('The author of a campaign is not blank and holds at most 191 characters.', static fn (): Campaign => new Campaign('c', Site::default(), 'l', 'Hello', 'Body', author: $name));
            $this->refused('The approver of a campaign is not blank and holds at most 191 characters.', fn (): CampaignApproval => new CampaignApproval($name, $this->at));
        }
    }

    public function testARunCountsAndCompletesOnceEveryMessageItQueuedIsSentOrSkipped(): void
    {
        $run = new SendRun(SendRun::idFor('c', 1), 'c', 1, $this->at);

        self::assertSame(32, \strlen($run->id));
        self::assertNotSame($run->id, SendRun::idFor('c', 2));
        self::assertNotSame($run->id, SendRun::idFor('d', 1));
        self::assertSame(substr(hash('sha256', "run\0c\0001"), 0, 32), $run->id);
        self::assertSame([SendRunStatus::Running, null, 0, null], [$run->status, $run->pauseReason, $run->queued, $run->queuedAt]);
        self::assertSame([SendRunCounter::Sent->value => 0, SendRunCounter::Failed->value => 0, SendRunCounter::Bounced->value => 0, SendRunCounter::Complained->value => 0], $run->baseline());

        foreach (SendRunCounter::cases() as $counter) {
            self::assertSame(0, $run->count($counter));
        }

        self::assertFalse($run->isDone(), 'Nothing is done before every message is queued.');
        $this->refused('A send run is completed once every message it queued was sent or skipped.', fn () => $run->complete($this->at));

        $run = SendRun::restore($run, SendRunStatus::Running, null, 3, [SendRunCounter::Sent->value => 2, SendRunCounter::Skipped->value => 1], [], null, null, null);
        self::assertFalse($run->isDone());
        $run->queuedAll(3, $this->at->modify('+1 minute'));
        self::assertSame([3, 1_800_000_060], [$run->queued, $run->queuedAt?->getTimestamp()]);
        self::assertTrue($run->isDone());
        $run->queuedAll(4, $this->at);
        self::assertFalse($run->isDone());
        $run->queuedAll(3, $this->at);

        $run->complete($this->at->modify('+2 minutes'));
        self::assertSame([SendRunStatus::Completed, 1_800_000_120], [$run->status, $run->endedAt?->getTimestamp()]);
        self::assertTrue($run->status->hasEnded());
        $this->refused('A send run that is completed cannot be cancelled.', fn () => $run->cancel($this->at));
        $this->refused('A send run that is completed cannot be paused.', fn () => $run->pause(PauseReason::Operator, $this->at));
    }

    public function testARunIsPausedResumedFromItsCountsAndCancelled(): void
    {
        $run = SendRun::restore(new SendRun('r', 'c', 2, $this->at), SendRunStatus::Running, null, 10, [SendRunCounter::Sent->value => 5, SendRunCounter::Failed->value => 2, SendRunCounter::Skipped->value => 1, SendRunCounter::Bounced->value => 1, SendRunCounter::Complained->value => 1], [], null, null, null);

        self::assertSame([SendRunCounter::Sent->value => 0, SendRunCounter::Failed->value => 0, SendRunCounter::Bounced->value => 0, SendRunCounter::Complained->value => 0], $run->baseline());
        $this->refused('A send run that is running cannot be resumed.', $run->resume(...));
        $run->pause(PauseReason::Bounces, $this->at);
        self::assertSame([SendRunStatus::Paused, PauseReason::Bounces, $this->at], [$run->status, $run->pauseReason, $run->pausedAt]);
        self::assertFalse($run->status->hasEnded());
        $this->refused('A send run that is paused cannot be paused.', fn () => $run->pause(PauseReason::Operator, $this->at));
        $this->refused('A send run that is paused cannot be completed.', fn () => $run->complete($this->at));
        self::assertSame(5, $run->sinceResume(SendRunCounter::Sent));

        $run->resume();
        self::assertSame([SendRunStatus::Running, null, null], [$run->status, $run->pauseReason, $run->pausedAt]);
        self::assertSame([SendRunCounter::Sent->value => 5, SendRunCounter::Failed->value => 2, SendRunCounter::Bounced->value => 1, SendRunCounter::Complained->value => 1], $run->baseline());

        foreach ([SendRunCounter::Sent, SendRunCounter::Failed, SendRunCounter::Bounced, SendRunCounter::Complained] as $counter) {
            self::assertSame(0, $run->sinceResume($counter));
        }

        self::assertSame(1, $run->sinceResume(SendRunCounter::Skipped), 'The skipped messages are not read by the breaker.');

        $run->pause(PauseReason::Operator, $this->at);
        $run->cancel($this->at->modify('+1 hour'));
        self::assertSame([SendRunStatus::Cancelled, 1_800_003_600], [$run->status, $run->endedAt?->getTimestamp()]);
        self::assertTrue($run->status->hasEnded());
        $this->refused('A send run that is cancelled cannot be cancelled.', fn () => $run->cancel($this->at));

        $running = new SendRun('r', 'c', 1, $this->at);
        $running->cancel($this->at);
        self::assertSame(SendRunStatus::Cancelled, $running->status);
        $this->refused('The number of a send run is positive.', fn (): SendRun => new SendRun('r', 'c', 0, $this->at));
    }

    /**
     * @param array<value-of<SendRunCounter>, int> $counts
     * @param array<value-of<SendRunCounter>, int> $baseline
     */
    #[DataProvider('breakers')]
    public function testTheBreakerTripsPastAThreshold(array $counts, array $baseline, ?PauseReason $expected): void
    {
        $run = SendRun::restore(new SendRun('r', 'c', 1, $this->at), SendRunStatus::Running, null, 1000, $counts, $baseline, null, null, null);

        self::assertSame($expected, new BreakerPolicy(minimum: 100, transportFailureRate: 10.0, bounceRate: 5.0, complaintRate: 0.3)->tripped($run));
    }

    /**
     * @return iterable<string, array{array<value-of<SendRunCounter>, int>, array<value-of<SendRunCounter>, int>, PauseReason|null}>
     */
    public static function breakers(): iterable
    {
        yield 'nothing yet' => [[], [], null];
        yield 'under the minimum, every attempt failed' => [['failed' => 99], [], null];
        yield 'transport failures at the threshold' => [['sent' => 90, 'failed' => 10], [], null];
        yield 'transport failures past the threshold' => [['sent' => 89, 'failed' => 11], [], PauseReason::TransportFailures];
        yield 'failures at the minimum of attempts' => [['sent' => 0, 'failed' => 100], [], PauseReason::TransportFailures];
        yield 'bounces at the threshold' => [['sent' => 100, 'bounced' => 5], [], null];
        yield 'bounces past the threshold' => [['sent' => 100, 'bounced' => 6], [], PauseReason::Bounces];
        yield 'bounces under the minimum of messages sent' => [['sent' => 99, 'bounced' => 50], [], null];
        yield 'complaints at the threshold' => [['sent' => 1000, 'complained' => 3], [], null];
        yield 'complaints past the threshold' => [['sent' => 1000, 'complained' => 4], [], PauseReason::Complaints];
        yield 'complaints before bounces' => [['sent' => 1000, 'complained' => 4, 'bounced' => 900], [], PauseReason::Complaints];
        yield 'bounces before transport failures' => [['sent' => 100, 'failed' => 100, 'bounced' => 6], [], PauseReason::Bounces];
        yield 'counted since the resumption' => [['sent' => 150, 'failed' => 60], ['sent' => 100, 'failed' => 50], null];
        yield 'past the threshold since the resumption' => [['sent' => 250, 'failed' => 100, 'bounced' => 15], ['sent' => 100, 'failed' => 50, 'bounced' => 10], PauseReason::TransportFailures];
        yield 'bounces since the resumption' => [['sent' => 200, 'bounced' => 16], ['sent' => 100, 'bounced' => 10], PauseReason::Bounces];
        yield 'complaints since the resumption' => [['sent' => 1100, 'complained' => 7], ['sent' => 100, 'complained' => 4], null];
    }

    public function testThePolicyOfTheBreaker(): void
    {
        $policy = new BreakerPolicy();
        self::assertSame([100, 10.0, 5.0, 0.3], [$policy->minimum, $policy->transportFailureRate, $policy->bounceRate, $policy->complaintRate]);
        self::assertSame(1, new BreakerPolicy(1, 0.0, 100.0, 0.0)->minimum);

        $this->refused('The minimum of a circuit breaker is positive.', static fn (): BreakerPolicy => new BreakerPolicy(0));

        foreach ([[-0.1, 5.0, 0.3], [10.0, 100.1, 0.3], [10.0, 5.0, -1.0]] as [$failures, $bounces, $complaints]) {
            $this->refused('A rate of a circuit breaker is a percentage, from 0 to 100.', static fn (): BreakerPolicy => new BreakerPolicy(100, $failures, $bounces, $complaints));
        }

        $run = SendRun::restore(new SendRun('r', 'c', 1, $this->at), SendRunStatus::Running, null, 1, [SendRunCounter::Sent->value => 1, SendRunCounter::Bounced->value => 1], [], null, null, null);
        self::assertSame(PauseReason::Bounces, new BreakerPolicy(1, 100.0, 99.9, 100.0)->tripped($run));
        self::assertNull(new BreakerPolicy(1, 100.0, 100.0, 100.0)->tripped($run));
    }

    private function refused(string $message, Closure $change): void
    {
        try {
            $change();
            self::fail('Expected a refusal: '.$message);
        } catch (InvalidNewsletterValue $invalidNewsletterValue) {
            self::assertSame($message, $invalidNewsletterValue->getMessage());
        }
    }
}
