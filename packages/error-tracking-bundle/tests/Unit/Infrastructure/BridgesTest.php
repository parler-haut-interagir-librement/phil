<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\ErrorTracking\ErrorGroup;
use Phil\ErrorTracking\GroupStatus;
use Phil\ErrorTracking\Occurrence;
use Phil\ErrorTrackingBundle\Infrastructure\Http\AdminController;
use Phil\ErrorTrackingBundle\Infrastructure\Monitoring\ErrorStoreCheck;
use Phil\ErrorTrackingBundle\Infrastructure\Notifications\ErrorGroupSubjects;
use Phil\ErrorTrackingBundle\Infrastructure\Notifications\NotificationAlerter;
use Phil\ErrorTrackingBundle\Infrastructure\RateLimit\LimiterCaptureThrottle;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\FixedUrls;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\MemoryStore;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\ErrorTrackingBundle\Tests\Unit\Fixture\RecordingReader;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\NotificationsBundle\Application\Handler\SendNotificationHandler;
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\NotificationTypes;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(ErrorStoreCheck::class)]
#[CoversClass(NotificationAlerter::class)]
#[CoversClass(ErrorGroupSubjects::class)]
#[CoversClass(LimiterCaptureThrottle::class)]
final class BridgesTest extends TestCase
{
    public function testTheCheckIsNamed(): void
    {
        self::assertSame('error_tracking', new ErrorStoreCheck(new MemoryStore(), new FrozenClock(), 20, 900)->name());
    }

    public function testAStoreThatDoesNotAnswerIsDegradedAndNamesNothing(): void
    {
        $outcome = new ErrorStoreCheck(new RecordingReader(new RuntimeException('SQLSTATE: db.internal refused')), new FrozenClock(), 20, 900)->check();

        self::assertSame(HealthStatus::Degraded, $outcome->status);
        self::assertSame('The error store does not answer.', $outcome->summary);
    }

    public function testTheNewGroupsAreCountedInTheirWindow(): void
    {
        $store = new MemoryStore();
        $this->group($store, 'a', '2026-11-08T09:59:00+00:00');
        $this->group($store, 'b', '2026-11-08T09:50:00+00:00');
        $this->group($store, 'c', '2026-11-08T09:49:59+00:00');

        $below = new ErrorStoreCheck($store, new FrozenClock(), 3, 600)->check();
        self::assertSame(HealthStatus::Up, $below->status);
        self::assertSame('2 new error groups in 600 seconds.', $below->summary);

        $at = new ErrorStoreCheck($store, new FrozenClock(), 2, 600)->check();
        self::assertSame(HealthStatus::Degraded, $at->status);
        self::assertSame('2 new error groups in 600 seconds.', $at->summary);
    }

    public function testTheGroupsReadAreBounded(): void
    {
        $reader = new RecordingReader();

        new ErrorStoreCheck($reader, new FrozenClock(), 5, 900)->check();
        new ErrorStoreCheck($reader, new FrozenClock(), 0, 900)->check();

        self::assertSame([[null, 200, 0], [null, 1, 0]], $reader->asked);
    }

    public function testWithoutThresholdTheCheckOnlyAsksTheStore(): void
    {
        $outcome = new ErrorStoreCheck(new MemoryStore(), new FrozenClock(), 0, 900)->check();

        self::assertSame(HealthStatus::Up, $outcome->status);
        self::assertSame('', $outcome->summary);
    }

    public function testAnAlertGoesToItsRecipientsWithIdentifiersOnly(): void
    {
        $bus = new RecordingBus();
        new NotificationAlerter($this->notifications($bus), ['ops', 'dev'])->alert($this->errorGroup(), Occurrence::First);

        $message = $bus->message();
        self::assertSame('phil_error_tracking.group_alert', $message->type);
        self::assertSame(['ops', 'dev'], $message->recipients);
        self::assertSame(['phil_error_tracking.group', str_repeat('a', 64)], [$message->subjectType, $message->subjectId]);
        self::assertSame(['occurrence' => 'first', 'seen' => 3], $message->data);
        self::assertNull($message->audience);
        self::assertStringNotContainsString('jane', serialize($message));
    }

    public function testAnAlertGoesToItsAudience(): void
    {
        $bus = new RecordingBus();
        new NotificationAlerter($this->notifications($bus), [], 'operators')->alert($this->errorGroup(), Occurrence::Regression);

        $message = $bus->message();
        self::assertSame('operators', $message->audience);
        self::assertSame([], $message->recipients);
        self::assertSame('regression', $message->data['occurrence']);
    }

    public function testTheSubjectOfAnAlertIsItsGroup(): void
    {
        $store = new MemoryStore();
        $store->groups[str_repeat('a', 64)] = $this->errorGroup();
        $urls = new FixedUrls();

        $subject = new ErrorGroupSubjects($store, $urls)->resolve(new SubjectReference(NotificationAlerter::SUBJECT, str_repeat('a', 64)), 'fr');

        self::assertSame('Boom', $subject?->label);
        self::assertSame('https://example.com/errors', $subject->url);
        self::assertSame([AdminController::SHOW_ROUTE, ['fingerprint' => str_repeat('a', 64)], UrlGeneratorInterface::ABSOLUTE_URL], $urls->asked);
    }

    public function testTheSubjectWithoutAdministration(): void
    {
        $store = new MemoryStore();
        $store->groups[str_repeat('a', 64)] = $this->errorGroup();

        self::assertNull(new ErrorGroupSubjects($store)->resolve(new SubjectReference(NotificationAlerter::SUBJECT, str_repeat('a', 64)), null)?->url);
        self::assertNull(new ErrorGroupSubjects($store, new FixedUrls(routed: false))->resolve(new SubjectReference(NotificationAlerter::SUBJECT, str_repeat('a', 64)), null)?->url);
    }

    public function testASubjectOfAnotherKindOrAPurgedGroupIsNotAnswered(): void
    {
        $store = new MemoryStore();
        $store->groups[str_repeat('a', 64)] = $this->errorGroup();
        $subjects = new ErrorGroupSubjects($store);

        self::assertNull($subjects->resolve(new SubjectReference('comment', str_repeat('a', 64)), null));
        self::assertNull($subjects->resolve(new SubjectReference(NotificationAlerter::SUBJECT, 'purged'), null));
    }

    public function testTheThrottleConsumesOneForTheDigest(): void
    {
        $request = new Request();
        $limiter = $this->limiter(true);

        self::assertTrue(new LimiterCaptureThrottle($limiter, new RequestStack([$request]))->allow('digest'));
        self::assertSame([['digest', 1, $request]], $limiter->consumed);
    }

    public function testTheThrottleRefusesWhatTheLimiterRefuses(): void
    {
        $limiter = $this->limiter(false);

        self::assertFalse(new LimiterCaptureThrottle($limiter, new RequestStack())->allow('digest'));
        self::assertInstanceOf(Request::class, $limiter->consumed[0][2], 'a limiter always gets a request');
    }

    private function notifications(MessageBusInterface $bus): Notifications
    {
        // The bus delivers: the handler, which needs a whole store, is never called.
        $handler = new ReflectionClass(SendNotificationHandler::class)->newInstanceWithoutConstructor();

        return new Notifications(new NotificationTypes(new NotificationType(NotificationAlerter::TYPE)), $handler, $bus);
    }

    private function errorGroup(): ErrorGroup
    {
        $seen = new DateTimeImmutable('2026-11-08T09:00:00+00:00');

        return new ErrorGroup(str_repeat('a', 64), 'Boom', null, GroupStatus::Open, 3, $seen, $seen);
    }

    private function group(MemoryStore $store, string $fingerprint, string $firstSeen): void
    {
        $seen = new DateTimeImmutable($firstSeen);
        $store->groups[$fingerprint] = new ErrorGroup($fingerprint, 'Secret title of jane', null, GroupStatus::Open, 1, $seen, $seen);
    }

    /**
     * @return Limiter&object{consumed: list<array{string, int, Request}>}
     */
    private function limiter(bool $accepted): Limiter
    {
        return new class($accepted) implements Limiter {
            /** @var list<array{string, int, Request}> */
            public array $consumed = [];

            public function __construct(private readonly bool $accepted)
            {
            }

            #[Override]
            public function consume(string $key, int $amount, Request $request): Decision
            {
                $this->consumed[] = [$key, $amount, $request];

                return new Decision($this->accepted, 1, 0, 1);
            }
        };
    }
}
