<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use Override;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\NewsletterBundle\Application\ApprovalPolicy;
use Phil\NewsletterBundle\Application\Bounces\BounceHandler;
use Phil\NewsletterBundle\Application\Campaigns;
use Phil\NewsletterBundle\Application\Event\CampaignApproved;
use Phil\NewsletterBundle\Application\Event\SendRunCompleted;
use Phil\NewsletterBundle\Application\Event\SendRunPaused;
use Phil\NewsletterBundle\Application\Event\SendRunResumed;
use Phil\NewsletterBundle\Application\Exception\ApprovalRefused;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Handler\DispatchDueCampaignsHandler;
use Phil\NewsletterBundle\Application\Handler\SendCampaignHandler;
use Phil\NewsletterBundle\Application\Handler\SendDeliveryHandler;
use Phil\NewsletterBundle\Application\ListRecipients;
use Phil\NewsletterBundle\Application\Message\DispatchDueCampaigns;
use Phil\NewsletterBundle\Application\Message\SendCampaign;
use Phil\NewsletterBundle\Application\Message\SendDelivery;
use Phil\NewsletterBundle\Application\Sending\MessageComposer;
use Phil\NewsletterBundle\Application\Sending\SenderProfile;
use Phil\NewsletterBundle\Application\Sending\SenderProfiles;
use Phil\NewsletterBundle\Application\SendRuns;
use Phil\NewsletterBundle\Application\Tracking\ClickTracker;
use Phil\NewsletterBundle\Domain\Contract\SendRunRepository;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;
use Phil\NewsletterBundle\Domain\Enum\PauseReason;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\SendRun;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\ValueObject\BreakerPolicy;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Site\ConfiguredSite;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use Phil\NewsletterBundle\Tests\Unit\Fixture\ScriptedThrottle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(SendRuns::class)]
#[CoversClass(Campaigns::class)]
#[CoversClass(ApprovalPolicy::class)]
#[CoversClass(ApprovalRefused::class)]
#[CoversClass(SendCampaignHandler::class)]
#[CoversClass(SendDeliveryHandler::class)]
#[CoversClass(BounceHandler::class)]
#[CoversClass(SendCampaign::class)]
#[CoversClass(CampaignApproved::class)]
#[CoversClass(SendRunPaused::class)]
#[CoversClass(SendRunResumed::class)]
#[CoversClass(SendRunCompleted::class)]
final class SendRunsTest extends TestCase
{
    private Newsletter $newsletter;

    private MailingList $list;

    /** @var list<string> */
    private array $granted = [];

    private TokenStorage $tokens;

    protected function setUp(): void
    {
        $this->newsletter = new Newsletter();
        $this->list = $this->newsletter->list('news', false);
        $this->tokens = new TokenStorage();
    }

    public function testACampaignIsApprovedByTheSignedInUserOfTheRoleAndRecorded(): void
    {
        $campaign = $this->campaign('c1', 'ann');
        $this->signIn('bob', 'ROLE_NEWSLETTER_APPROVER');
        $this->newsletter->clock->modify('+5 minutes');

        $this->campaigns(new ApprovalPolicy(fourEyes: true))->approve($campaign);

        $stored = $this->newsletter->campaigns->get('c1');
        self::assertSame(['bob', $this->newsletter->clock->now()->getTimestamp()], [$stored?->approval?->approver, $stored?->approval?->approvedAt->getTimestamp()]);
        self::assertEquals([new CampaignApproved('c1', $this->newsletter->clock->now())], $this->events(CampaignApproved::class));

        $this->campaigns()->withdrawApproval($campaign);
        self::assertNull($this->newsletter->campaigns->get('c1')?->approval);
    }

    public function testAnApprovalIsRefusedWithoutTheRoleASignedInUserOrAnotherPairOfEyes(): void
    {
        $campaign = $this->campaign('c1', 'ann');
        $refusals = [
            'Approving a campaign takes the role "ROLE_EDITOR".' => fn () => new Campaigns($this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->bus, $this->newsletter->clock, $this->newsletter->sendRuns(), new ApprovalPolicy(role: 'ROLE_EDITOR'))->approve($campaign),
            'Approving a campaign takes the role "ROLE_NEWSLETTER_APPROVER".' => fn () => $this->campaigns()->approve($campaign),
        ];

        foreach ($refusals as $message => $approve) {
            $this->refused($message, $approve);
        }

        $this->granted = ['ROLE_NEWSLETTER_APPROVER'];
        $this->refused('A campaign is approved by a signed-in user.', fn () => $this->campaigns()->approve($campaign));

        $this->signIn('ann', 'ROLE_NEWSLETTER_APPROVER');
        $this->refused('Under the four-eyes rule, a campaign is approved by someone other than its author.', fn () => $this->campaigns(new ApprovalPolicy(fourEyes: true))->approve($campaign));
        $this->refused('Under the four-eyes rule, a campaign is approved by someone other than its author.', fn () => $this->campaigns(new ApprovalPolicy(fourEyes: true))->approve($this->campaign('c2')));
        self::assertNull($this->newsletter->campaigns->get('c1')?->approval);
        self::assertSame([], $this->events(CampaignApproved::class));

        $this->campaigns()->approve($campaign);
        self::assertSame('ann', $this->newsletter->campaigns->get('c1')?->approval?->approver);

        $this->expectException(InvalidNewsletterValue::class);
        $this->expectExceptionMessageIsOrContains('The role that approves campaigns is not blank.');
        new ApprovalPolicy(role: ' ');
    }

    public function testACampaignAwaitingItsApprovalIsNeitherSentNorStartedAtItsTime(): void
    {
        $this->subscribe('ada@example.com');
        $draft = $this->campaign('c1');
        $this->refused('A campaign is sent once it is approved.', fn () => $this->campaigns()->sendNow($draft));
        self::assertSame(CampaignStatus::Draft, $this->newsletter->campaigns->get('c1')?->status);
        self::assertSame([], $this->newsletter->runs->ofCampaign('c1'));

        $this->campaignHandler()(new SendCampaign('c1'));
        self::assertSame(CampaignStatus::Draft, $this->newsletter->campaigns->get('c1')?->status);
        self::assertSame([], $this->newsletter->bus->messages(SendDelivery::class));

        $waiting = $this->campaign('c2');
        $approved = $this->campaign('c3');
        $approved->approve('ann', $this->newsletter->clock->now(), false);
        $this->campaigns()->schedule($waiting, $this->newsletter->clock->now());
        $this->campaigns()->schedule($approved, $this->newsletter->clock->now());

        new DispatchDueCampaignsHandler($this->campaigns())(new DispatchDueCampaigns());
        self::assertSame(CampaignStatus::Scheduled, $this->newsletter->campaigns->get('c2')?->status);
        self::assertSame(CampaignStatus::Sending, $this->newsletter->campaigns->get('c3')?->status);
        self::assertSame(0, $this->campaigns()->startDue());
        self::assertSame(1, $this->campaigns(new ApprovalPolicy(required: false))->startDue());
        self::assertSame(CampaignStatus::Sending, $this->newsletter->campaigns->get('c2')?->status);
    }

    public function testASendRunQueuesEachRecipientOnceCountsWhatBecameOfThemAndCompletes(): void
    {
        $this->subscribe('ada@example.com');
        $this->subscribe('bob@example.com');
        $carl = $this->subscribe('carl@example.com');
        $campaign = $this->approved('c1');

        $this->campaigns()->sendNow($campaign);
        $id = SendRun::idFor('c1', 1);
        self::assertEquals([new SendCampaign('c1', $id)], $this->newsletter->bus->messages(SendCampaign::class));
        self::assertSame([1], array_map(static fn (SendRun $run): int => $run->number, $this->runs()->ofCampaign('c1')));
        self::assertSame(SendRunStatus::Running, $this->runs()->get($id)->status);

        $this->campaignHandler()(new SendCampaign('c1', $id));
        $this->campaignHandler()(new SendCampaign('c1', $id));
        $run = $this->runs()->get($id);
        self::assertSame([3, $this->newsletter->clock->now()->getTimestamp()], [$run->queued, $run->queuedAt?->getTimestamp()]);
        self::assertSame($id, $this->newsletter->deliveries->get(Delivery::idFor('c1', $carl->id))?->runId);
        self::assertCount(3, $this->newsletter->bus->messages(SendDelivery::class));

        $this->newsletter->subscriptions->leave($carl->id, $this->list->id);
        $this->deliverAll();
        $this->deliverAll();

        $run = $this->runs()->get($id);
        self::assertSame([SendRunStatus::Completed, 2, 1, 0], [$run->status, $run->count(SendRunCounter::Sent), $run->count(SendRunCounter::Skipped), $run->count(SendRunCounter::Failed)]);
        self::assertSame($this->newsletter->clock->now()->getTimestamp(), $run->endedAt?->getTimestamp());
        self::assertEquals([new SendRunCompleted($id, 'c1', $this->newsletter->clock->now())], $this->events(SendRunCompleted::class));
        self::assertCount(2, $this->newsletter->mailer->sent);
    }

    public function testACampaignWithoutRecipientsCompletesItsRunOnceQueued(): void
    {
        $this->newsletter->campaigns->save($this->approved('c1'));
        $this->campaignHandler()(new SendCampaign('c1'));

        [$run] = $this->runs()->ofCampaign('c1');
        self::assertSame([SendRunStatus::Completed, 0], [$run->status, $run->queued]);
        self::assertSame(CampaignStatus::Sent, $this->newsletter->campaigns->get('c1')?->status);
    }

    public function testTheRunOfAMessageWithoutOneIsTheRunStillQueuingElseANewOne(): void
    {
        $campaign = $this->approved('c1');
        $runs = $this->runs();

        $first = $runs->current($campaign);
        self::assertSame(1, $first->number);
        self::assertEquals($first, $runs->current($campaign), 'The run still queuing is the current one.');

        $runs->queuedAll($first->id);
        self::assertSame(2, $runs->current($campaign)->number);
        $runs->cancel(SendRun::idFor('c1', 2));
        self::assertSame(3, $runs->current($campaign)->number);
        self::assertSame(4, $runs->start($campaign)->number);
        self::assertSame([1, 2, 3, 4], array_map(static fn (SendRun $run): int => $run->number, $runs->ofCampaign('c1')));
        self::assertNull($runs->find('none'));
        self::assertNull($runs->status(null));
        self::assertNull($runs->status('none'));
        self::assertSame(SendRunStatus::Cancelled, $runs->status(SendRun::idFor('c1', 2)));

        $events = \count($this->newsletter->events->events);
        $runs->record(null, SendRunCounter::Sent);
        $runs->record('none', SendRunCounter::Sent);
        self::assertCount($events, $this->newsletter->events->events);
        self::assertSame(0, $runs->get($first->id)->count(SendRunCounter::Sent));

        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('No send run "none".');
        $runs->queuedAll('none');
    }

    public function testTransportFailuresPauseTheRunWhichHoldsItsMessagesUntilAnOperatorResumesIt(): void
    {
        foreach (['ada', 'bob', 'carl', 'dee'] as $name) {
            $this->subscribe($name.'@example.com');
        }

        $this->campaignHandler($this->runs(new BreakerPolicy(minimum: 2, transportFailureRate: 40.0)))(new SendCampaign($this->approved('c1')->id));
        $id = SendRun::idFor('c1', 1);
        $messages = $this->newsletter->bus->messages(SendDelivery::class);
        $handler = $this->deliveryHandler(new BreakerPolicy(minimum: 2, transportFailureRate: 40.0));
        $this->newsletter->mailer->failures = 2;

        foreach ([$messages[0], $messages[1]] as $message) {
            try {
                $handler($message);
                self::fail('Expected a transport failure.');
            } catch (TransportExceptionInterface) {
            }
        }

        $run = $this->runs()->get($id);
        self::assertSame([SendRunStatus::Paused, PauseReason::TransportFailures, 2], [$run->status, $run->pauseReason, $run->count(SendRunCounter::Failed)]);
        self::assertEquals([new SendRunPaused($id, 'c1', PauseReason::TransportFailures, $this->newsletter->clock->now())], $this->events(SendRunPaused::class));

        $handler($messages[2]);
        $handler($messages[0]);
        self::assertSame([], $this->newsletter->mailer->sent, 'A paused run sends nothing.');
        self::assertSame(DeliveryStatus::Queued, $this->newsletter->deliveries->get($messages[2]->deliveryId)?->status);
        self::assertSame(0, $this->runs()->get($id)->count(SendRunCounter::Skipped));

        $before = \count($this->newsletter->bus->messages(SendDelivery::class));
        $this->runs(new BreakerPolicy(minimum: 2, transportFailureRate: 40.0))->resume($id);
        $queuedAgain = \array_slice($this->newsletter->bus->messages(SendDelivery::class), $before);
        self::assertCount(4, $queuedAgain, 'The failed and the queued messages are queued again.');
        self::assertEquals([new SendRunResumed($id, 'c1', $this->newsletter->clock->now())], $this->events(SendRunResumed::class));
        $resumed = $this->runs()->get($id);
        self::assertSame([SendRunStatus::Running, null, 0], [$resumed->status, $resumed->pauseReason, $resumed->sinceResume(SendRunCounter::Failed)]);

        foreach ($queuedAgain as $message) {
            $handler($message);
        }

        $run = $this->runs()->get($id);
        self::assertSame([SendRunStatus::Completed, 4, 2], [$run->status, $run->count(SendRunCounter::Sent), $run->count(SendRunCounter::Failed)]);
        self::assertCount(1, $this->events(SendRunPaused::class));
    }

    public function testBouncesAndComplaintsAreCountedOnceAndPauseTheRun(): void
    {
        $ada = $this->subscribe('ada@example.com');
        $bob = $this->subscribe('bob@example.com');
        $breaker = new BreakerPolicy(minimum: 2, bounceRate: 40.0, complaintRate: 40.0);
        $this->campaignHandler($this->runs($breaker))(new SendCampaign($this->approved('c1')->id));
        $id = SendRun::idFor('c1', 1);
        $this->deliverAll($breaker);
        $bounces = new BounceHandler(new BounceParser(), $this->newsletter->subscribers, $this->newsletter->deliveries, $this->newsletter->suppressions, $this->newsletter->subscriptions, new ConfiguredSite(), $this->newsletter->clock, null, '', 3, $this->runs($breaker));

        $bounces->handleEvent(BounceKind::Hard, 'ada@example.com', Delivery::idFor('c1', $ada->id));
        $bounces->handleEvent(BounceKind::Hard, 'ada@example.com', Delivery::idFor('c1', $ada->id));
        $bounces->handleEvent(BounceKind::Soft, 'bob@example.com', Delivery::idFor('c1', $bob->id));
        $bounces->handleEvent(BounceKind::Hard, 'nobody@example.com', null);

        $run = $this->runs()->get($id);
        self::assertSame([SendRunStatus::Completed, 1, 0], [$run->status, $run->count(SendRunCounter::Bounced), $run->count(SendRunCounter::Complained)]);
        self::assertSame([], $this->events(SendRunPaused::class), 'A completed run is no longer paused.');

        $this->campaignHandler($this->runs($breaker))(new SendCampaign($this->approved('c2')->id));
        $this->deliverAll($breaker);
        $bounces->handleEvent(BounceKind::Complaint, 'ada@example.com', Delivery::idFor('c2', $ada->id));
        self::assertSame([0, 1], [$this->runs()->get(SendRun::idFor('c2', 1))->count(SendRunCounter::Complained), $this->runs()->get(SendRun::idFor('c2', 1))->count(SendRunCounter::Sent)], 'The suppressed address was no recipient: its complaint names no delivery of the run.');

        $this->newsletter->deliveries->create(new Delivery('d1', $bob->id, 'c2', null, $this->newsletter->clock->now(), 'r1'));
        $this->newsletter->deliveries->create(new Delivery('d2', $bob->id, 'c2', null, $this->newsletter->clock->now(), 'r1'));
        $this->newsletter->runs->create(SendRun::restore(new SendRun('r1', 'c2', 9, $this->newsletter->clock->now()), SendRunStatus::Running, null, 5, [SendRunCounter::Sent->value => 2], [], null, null, null));

        $bounces->handleEvent(BounceKind::Complaint, 'bob@example.com', 'd1');

        $run = $this->runs()->get('r1');
        self::assertSame([SendRunStatus::Paused, PauseReason::Complaints, 1, 0], [$run->status, $run->pauseReason, $run->count(SendRunCounter::Complained), $run->count(SendRunCounter::Bounced)]);
        self::assertEquals([new SendRunPaused('r1', 'c2', PauseReason::Complaints, $this->newsletter->clock->now())], $this->events(SendRunPaused::class));
    }

    public function testAnOperatorPausesAndCancelsARunWhoseMessagesAreThenSkipped(): void
    {
        foreach (['ada', 'bob', 'carl'] as $name) {
            $this->subscribe($name.'@example.com');
        }

        $this->campaignHandler()(new SendCampaign($this->approved('c1')->id));
        $id = SendRun::idFor('c1', 1);
        [$first, $second, $third] = $this->newsletter->bus->messages(SendDelivery::class);
        $this->deliveryHandler()($first);

        $this->runs()->pause($id);
        self::assertSame(PauseReason::Operator, $this->runs()->get($id)->pauseReason);
        self::assertEquals([new SendRunPaused($id, 'c1', PauseReason::Operator, $this->newsletter->clock->now())], $this->events(SendRunPaused::class));
        $this->refused('A send run that is paused cannot be paused.', fn () => $this->runs()->pause($id));

        $this->runs()->cancel($id);
        $run = $this->runs()->get($id);
        self::assertSame([SendRunStatus::Cancelled, 1, 2], [$run->status, $run->count(SendRunCounter::Sent), $run->count(SendRunCounter::Skipped)]);
        self::assertSame(DeliveryStatus::Skipped, $this->newsletter->deliveries->get($second->deliveryId)?->status);
        $this->refused('A send run that is cancelled cannot be cancelled.', fn () => $this->runs()->cancel($id));
        $this->refused('A send run that is cancelled cannot be resumed.', fn () => $this->runs()->resume($id));

        $late = $this->newsletter->deliveries->get($third->deliveryId);
        self::assertInstanceOf(Delivery::class, $late);
        $this->newsletter->deliveries->save(Delivery::restore($late, DeliveryStatus::Queued, 0, null));
        $this->deliveryHandler()($third);
        self::assertSame(DeliveryStatus::Skipped, $this->newsletter->deliveries->get($third->deliveryId)?->status);
        self::assertSame(3, $this->runs()->get($id)->count(SendRunCounter::Skipped));
        self::assertCount(1, $this->newsletter->mailer->sent);

        $this->campaignHandler()(new SendCampaign('c1', $id));
        self::assertCount(3, $this->newsletter->bus->messages(SendDelivery::class), 'The campaign is sent already.');
        self::assertSame([], $this->events(SendRunCompleted::class));
    }

    public function testACampaignWhoseRunWasCancelledWhileQueuingQueuesNothing(): void
    {
        $this->subscribe('ada@example.com');
        $campaign = $this->approved('c1');
        $campaign->start();

        $this->newsletter->campaigns->save($campaign);
        $run = $this->runs()->start($campaign);
        $this->runs()->cancel($run->id);

        $this->campaignHandler()(new SendCampaign('c1', $run->id));

        self::assertSame([], $this->newsletter->bus->messages(SendDelivery::class));
        self::assertSame(CampaignStatus::Sent, $this->newsletter->campaigns->get('c1')?->status);
        self::assertNull($this->runs()->get($run->id)->queuedAt);
    }

    public function testAResumedRunQueuesAgainWhatItHoldsInPages(): void
    {
        $run = SendRun::restore(new SendRun('r1', 'c1', 1, $this->newsletter->clock->now()), SendRunStatus::Paused, PauseReason::Operator, 0, [], [], $this->newsletter->clock->now(), $this->newsletter->clock->now(), null);
        $this->newsletter->runs->create($run);

        for ($i = 0; $i <= SendRuns::PAGE; ++$i) {
            $this->newsletter->deliveries->create(new Delivery(\sprintf('d%04d', $i), 's'.$i, 'c1', null, $this->newsletter->clock->now(), 'r1'));
        }

        $this->newsletter->runs->queuedAll(SendRun::restore(new SendRun('r1', 'c1', 1, $this->newsletter->clock->now()), SendRunStatus::Paused, null, SendRuns::PAGE + 1, [], [], $this->newsletter->clock->now(), null, null));
        $this->runs()->resume('r1');

        $ids = array_map(static fn (SendDelivery $message): string => $message->deliveryId, $this->newsletter->bus->messages(SendDelivery::class));
        self::assertCount(SendRuns::PAGE + 1, $ids);
        self::assertCount(SendRuns::PAGE + 1, array_unique($ids));
        self::assertSame(SendRunStatus::Running, $this->runs()->get('r1')->status);
    }

    public function testAResumedRunWithNothingLeftCompletes(): void
    {
        $now = $this->newsletter->clock->now();
        $this->newsletter->runs->create(SendRun::restore(new SendRun('r1', 'c1', 1, $now), SendRunStatus::Paused, PauseReason::Operator, 1, [SendRunCounter::Sent->value => 1], [], $now, $now, null));

        $this->runs()->resume('r1');

        self::assertSame(SendRunStatus::Completed, $this->runs()->get('r1')->status);
        self::assertSame([], $this->newsletter->bus->messages(SendDelivery::class));
    }

    public function testARunThatTripsOnItsLastMessageStaysPaused(): void
    {
        $now = $this->newsletter->clock->now();
        $this->newsletter->runs->create(SendRun::restore(new SendRun('r1', 'c1', 1, $now), SendRunStatus::Running, null, 2, [SendRunCounter::Sent->value => 2], [], $now, null, null));

        $this->runs(new BreakerPolicy(minimum: 1))->record('r1', SendRunCounter::Complained);

        $run = $this->runs()->get('r1');
        self::assertSame([SendRunStatus::Paused, PauseReason::Complaints, null], [$run->status, $run->pauseReason, $run->endedAt]);
        self::assertSame([], $this->events(SendRunCompleted::class));
    }

    public function testAWorkerThatLosesARaceForTheStateDispatchesNothing(): void
    {
        $now = $this->newsletter->clock->now();
        $repository = $this->racingRepository();
        $runs = new SendRuns($repository, $this->newsletter->deliveries, $this->newsletter->bus, $this->newsletter->events, $this->newsletter->clock, new BreakerPolicy(minimum: 1));
        $this->newsletter->runs->create(SendRun::restore(new SendRun('done', 'c1', 1, $now), SendRunStatus::Running, null, 1, [], [], $now, null, null));
        $this->newsletter->runs->create(SendRun::restore(new SendRun('failing', 'c1', 2, $now), SendRunStatus::Running, null, 9, [], [], $now, null, null));
        $this->newsletter->runs->create(SendRun::restore(new SendRun('paused', 'c1', 3, $now), SendRunStatus::Paused, PauseReason::Operator, 9, [], [], $now, $now, null));
        $this->newsletter->runs->create(SendRun::restore(new SendRun('held', 'c1', 4, $now), SendRunStatus::Running, null, 9, [], [], null, null, null));
        $this->newsletter->deliveries->create(new Delivery('d1', 's1', 'c1', null, $now, 'paused'));
        $this->newsletter->deliveries->create(new Delivery('d2', 's1', 'c1', null, $now, 'held'));

        $runs->record('done', SendRunCounter::Sent);
        $runs->record('failing', SendRunCounter::Failed);
        $runs->resume('paused');
        $runs->pause('held');
        $runs->cancel('held');

        self::assertSame([], $this->newsletter->events->events);
        self::assertSame([], $this->newsletter->bus->messages(SendDelivery::class));
        self::assertSame(DeliveryStatus::Queued, $this->newsletter->deliveries->get('d2')?->status);
        self::assertSame(0, $this->runs()->get('held')->count(SendRunCounter::Skipped));
    }

    private function racingRepository(): SendRunRepository
    {
        return new readonly class($this->newsletter->runs) implements SendRunRepository {
            public function __construct(
                private SendRunRepository $inner,
            ) {
            }

            #[Override]
            public function create(SendRun $run): bool
            {
                return $this->inner->create($run);
            }

            #[Override]
            public function get(string $id): ?SendRun
            {
                return $this->inner->get($id);
            }

            #[Override]
            public function ofCampaign(string $campaignId): array
            {
                return $this->inner->ofCampaign($campaignId);
            }

            #[Override]
            public function add(string $runId, SendRunCounter $counter, int $by = 1): void
            {
                $this->inner->add($runId, $counter, $by);
            }

            #[Override]
            public function queuedAll(SendRun $run): void
            {
                $this->inner->queuedAll($run);
            }

            #[Override]
            public function transition(SendRun $run, SendRunStatus $from): bool
            {
                return false;
            }
        };
    }

    private function campaign(string $id, ?string $author = null): Campaign
    {
        $campaign = new Campaign($id, Site::default(), $this->list->id, 'Hello', 'Hi', author: $author);
        $this->newsletter->campaigns->save($campaign);

        return $campaign;
    }

    private function approved(string $id): Campaign
    {
        $campaign = $this->campaign($id, 'ann');
        $campaign->approve('bob', $this->newsletter->clock->now(), true);

        $this->newsletter->campaigns->save($campaign);

        return $campaign;
    }

    private function subscribe(string $email): Subscriber
    {
        $this->newsletter->subscriptions->subscribe($this->list, $email);
        $subscriber = $this->newsletter->subscribers->byEmail(Site::default(), $email);
        self::assertInstanceOf(Subscriber::class, $subscriber);

        return $subscriber;
    }

    private function signIn(string $user, string ...$roles): void
    {
        $this->granted = array_values($roles);
        $this->tokens->setToken(new UsernamePasswordToken(new InMemoryUser($user, null, $this->granted), 'main', $this->granted));
    }

    private function campaigns(ApprovalPolicy $policy = new ApprovalPolicy()): Campaigns
    {
        $authorization = new readonly class($this->granted) implements AuthorizationCheckerInterface {
            /**
             * @param list<string> $granted
             */
            public function __construct(
                private array $granted,
            ) {
            }

            #[Override]
            public function isGranted(mixed $attribute, mixed $subject = null, mixed $accessDecision = null): bool
            {
                return \in_array($attribute, $this->granted, true);
            }
        };

        return new Campaigns($this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->bus, $this->newsletter->clock, $this->newsletter->sendRuns(), $policy, $authorization, $this->tokens, $this->newsletter->events);
    }

    private function runs(?BreakerPolicy $breaker = null): SendRuns
    {
        return $this->newsletter->sendRuns($breaker);
    }

    private function campaignHandler(?SendRuns $runs = null): SendCampaignHandler
    {
        return new SendCampaignHandler($this->newsletter->campaigns, $this->newsletter->deliveries, new ListRecipients($this->newsletter->subscribers), $this->newsletter->bus, $this->newsletter->clock, $runs ?? $this->runs());
    }

    private function deliveryHandler(?BreakerPolicy $breaker = null): SendDeliveryHandler
    {
        return new SendDeliveryHandler(
            $this->newsletter->deliveries,
            $this->newsletter->subscribers,
            $this->newsletter->campaigns,
            $this->newsletter->lists,
            $this->newsletter->templates,
            $this->newsletter->suppressions,
            new SenderProfiles([new SenderProfile('default', 'news@example.com')], 'default'),
            new ScriptedThrottle(),
            $this->newsletter->renderer(),
            new MessageComposer(null, ''),
            new ClickTracker($this->newsletter->links, $this->newsletter->campaigns, $this->newsletter->deliveries, $this->newsletter->engagement, $this->newsletter->events, $this->newsletter->clock),
            $this->newsletter->links,
            $this->newsletter->urls(),
            $this->newsletter->mailer,
            $this->newsletter->bus,
            $this->newsletter->clock,
            $this->runs($breaker),
        );
    }

    private function deliverAll(?BreakerPolicy $breaker = null): void
    {
        foreach ($this->newsletter->bus->messages(SendDelivery::class) as $message) {
            $this->deliveryHandler($breaker)($message);
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function events(string $class): array
    {
        return array_values(array_filter($this->newsletter->events->events, static fn (object $event): bool => $event instanceof $class));
    }

    private function refused(string $message, callable $change): void
    {
        try {
            $change();
            self::fail('Expected a refusal: '.$message);
        } catch (ApprovalRefused|InvalidNewsletterValue $refused) {
            self::assertSame($message, $refused->getMessage());
        }
    }
}
