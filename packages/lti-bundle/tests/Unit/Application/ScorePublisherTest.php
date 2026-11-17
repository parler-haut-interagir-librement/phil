<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Phil\Lti\Contract\ServiceClient;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Exception\TokenRequestRefused;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\ServiceResponse;
use Phil\Lti\Key\InMemoryKeyStore;
use Phil\Lti\Key\RsaPrivateKey;
use Phil\Lti\Message\AgsEndpoint;
use Phil\Lti\Message\Launch;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Registration\InMemoryPlatformRegistrations;
use Phil\Lti\Registration\PlatformRegistration;
use Phil\Lti\Service\AccessTokenProvider;
use Phil\Lti\Service\Ags\ActivityProgress;
use Phil\Lti\Service\Ags\AgsClient;
use Phil\Lti\Service\Ags\GradingProgress;
use Phil\Lti\Service\Ags\Score;
use Phil\Lti\Service\Scope;
use Phil\Lti\Service\ServiceCall;
use Phil\LtiBundle\Application\Event\ScorePublished;
use Phil\LtiBundle\Application\Event\ScoreRefused;
use Phil\LtiBundle\Application\Message\PublishScore;
use Phil\LtiBundle\Application\PendingScore;
use Phil\LtiBundle\Application\ScorePublisher;
use Phil\LtiBundle\Infrastructure\Console\RecoverScoresCommand;
use Phil\LtiBundle\Infrastructure\Memory\InMemoryPendingScores;
use Phil\LtiBundle\Infrastructure\Messenger\PublishScoreHandler;
use Phil\LtiBundle\Tests\Unit\Fixture\RecordingBus;
use Phil\LtiBundle\Tests\Unit\Fixture\TestKeys;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

#[CoversClass(ScorePublisher::class)]
#[CoversClass(PendingScore::class)]
#[CoversClass(PublishScore::class)]
#[CoversClass(ScorePublished::class)]
#[CoversClass(ScoreRefused::class)]
#[CoversClass(RecoverScoresCommand::class)]
#[CoversClass(PublishScoreHandler::class)]
#[CoversClass(InMemoryPendingScores::class)]
final class ScorePublisherTest extends TestCase
{
    private const string ITEM = 'https://lms.example.org/c/1/lineitems/9';

    private InMemoryPendingScores $pending;

    private InMemoryPlatformRegistrations $platforms;

    /** @var list<ServiceRequest> */
    private array $requests = [];

    private int $status = 204;

    private ?Throwable $failure = null;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->pending = new InMemoryPendingScores();
        $this->clock = new MockClock('2026-11-14 12:00:00 UTC');
        $this->platforms = new InMemoryPlatformRegistrations()->add(new PlatformRegistration('https://lms.example.org', 'client', Url::fromString('https://lms.example.org/auth'), Url::fromString('https://lms.example.org/token'), Url::fromString('https://lms.example.org/jwks')));
    }

    public function testAScoreIsSentAtOnceWithoutABus(): void
    {
        $events = new EventDispatcher();
        $published = [];
        $events->addListener(ScorePublished::class, static function (ScorePublished $event) use (&$published): void {
            $published[] = $event->score;
        });

        $id = $this->publisher(events: $events)->publish($this->launch(), $this->score());

        self::assertSame(32, \strlen($id));
        self::assertNull($this->pending->find($id));
        self::assertSame(self::ITEM.'/scores', $this->scoreRequests()[0]->url->toString());
        self::assertCount(1, $published);
        self::assertSame($id, $published[0]->id);
        self::assertSame(self::ITEM, $published[0]->lineItem->toString());
        self::assertEquals($this->clock->now(), $published[0]->createdAt);
    }

    public function testABusCarriesTheIdentifierOnly(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message);
            }
        };
        $publisher = $this->publisher($bus);
        $id = $publisher->publish($this->launch(), $this->score(), Url::fromString('https://lms.example.org/c/1/lineitems/3'));

        self::assertEquals([new PublishScore($id)], $bus->messages);
        self::assertSame([], $this->requests);
        self::assertSame('https://lms.example.org/c/1/lineitems/3', $this->pending->find($id)?->lineItem->toString());

        new PublishScoreHandler($publisher)(new PublishScore($id));
        self::assertNull($this->pending->find($id));
        self::assertSame('https://lms.example.org/c/1/lineitems/3/scores', $this->scoreRequests()[0]->url->toString());

        new PublishScoreHandler($publisher)(new PublishScore($id));
        self::assertCount(1, $this->scoreRequests());
    }

    public function testALaunchWithoutTheScopeOrALineItemIsRefused(): void
    {
        foreach ([null, new AgsEndpoint([Scope::LINE_ITEM], null, Url::fromString(self::ITEM)), new AgsEndpoint([Scope::SCORE])] as $endpoint) {
            try {
                $this->publisher()->publish($this->launch($endpoint), $this->score());
                self::fail('A score was kept.');
            } catch (InvalidRequest) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame([], $this->requests);
    }

    public function testFailuresAreRetriedOrDropped(): void
    {
        $bus = new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return new Envelope($message);
            }
        };
        $publisher = $this->publisher($bus);
        $handler = new PublishScoreHandler($publisher);

        foreach ([500 => ServiceFailed::class, 429 => ServiceFailed::class, 408 => ServiceFailed::class, 400 => UnrecoverableMessageHandlingException::class, 404 => UnrecoverableMessageHandlingException::class, 499 => UnrecoverableMessageHandlingException::class, 399 => ServiceFailed::class] as $status => $class) {
            $this->status = $status;
            $id = $publisher->publish($this->launch(), $this->score());

            try {
                $handler(new PublishScore($id));
                self::fail((string) $status);
            } catch (ServiceFailed|UnrecoverableMessageHandlingException $exception) {
                self::assertInstanceOf($class, $exception, (string) $status);

                if ($exception instanceof UnrecoverableMessageHandlingException) {
                    self::assertSame(0, $exception->getCode(), (string) $status);
                }

                self::assertNotNull($this->pending->find($id), 'A score that failed stays pending.');
            }
        }

        $this->status = 204;
        $id = $publisher->publish($this->launch(issuer: 'https://gone.example.org'), $this->score());

        try {
            $handler(new PublishScore($id));
            self::fail('A score of an unknown platform was sent.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertInstanceOf(UnknownRegistration::class, $unrecoverableMessageHandlingException->getPrevious());
            self::assertSame(0, $unrecoverableMessageHandlingException->getCode());
        }

        self::assertEquals($this->clock->now(), $this->pending->find($id)?->refusedAt, 'A score of an unknown platform is kept as refused.');

        try {
            $publisher->send($publisher->publish($this->launch(issuer: 'https://gone.example.org'), $this->score()));
            self::fail('A score of an unknown platform was sent.');
        } catch (UnknownRegistration) {
            self::addToAssertionCount(1);
        }
    }

    public function testARefusalForGoodIsKeptWithItsReasonAndToldOnce(): void
    {
        $events = new EventDispatcher();
        $refused = [];
        $events->addListener(ScoreRefused::class, static function (ScoreRefused $event) use (&$refused): void {
            $refused[] = $event->score;
        });
        $publisher = $this->publisher($this->bus(), $events);
        $id = $publisher->publish($this->launch(), $this->score());
        $this->clock->sleep(5);
        $this->status = 404;

        try {
            $publisher->send($id);
            self::fail('A refused score was taken.');
        } catch (ServiceFailed $serviceFailed) {
            self::assertSame(404, $serviceFailed->status());
        }

        $kept = $this->pending->find($id);
        self::assertNotNull($kept);
        self::assertEquals($this->clock->now(), $kept->refusedAt);
        self::assertSame($serviceFailed->getMessage(), $kept->refusal);
        self::assertCount(1, $refused);
        self::assertEquals($kept, $refused[0]);
        self::assertEquals($this->clock->now()->modify('-5 seconds'), $kept->createdAt);

        $requests = \count($this->scoreRequests());
        $publisher->send($id);
        new PublishScoreHandler($publisher)(new PublishScore($id));
        self::assertCount($requests, $this->scoreRequests(), 'A refused score is never sent again.');
        self::assertCount(1, $refused);
        self::assertSame(0, $publisher->recover(1), 'A refused score is never recovered.');
    }

    public function testAFailureThatMayPassLeavesTheScorePending(): void
    {
        $publisher = $this->publisher($this->bus(), new EventDispatcher());
        $id = $publisher->publish($this->launch(), $this->score());

        foreach ([500, 429, 408] as $status) {
            $this->status = $status;

            try {
                $publisher->send($id);
                self::fail((string) $status);
            } catch (ServiceFailed) {
                self::assertNull($this->pending->find($id)?->refusedAt, (string) $status);
            }
        }

        $this->failure = TokenRequestRefused::invalidRequest('Later.');

        try {
            $publisher->send($id);
            self::fail('The token was given.');
        } catch (TokenRequestRefused) {
            self::assertNull($this->pending->find($id)?->refusedAt);
        }
    }

    public function testTheFailuresThePlatformWillRepeatAreFinal(): void
    {
        foreach ([400 => true, 401 => true, 404 => true, 499 => true, 408 => false, 429 => false, 399 => false, 500 => false, 0 => false] as $status => $final) {
            self::assertSame($final, ScorePublisher::isFinal(ServiceFailed::because('x', null, $status)), (string) $status);
        }

        self::assertTrue(ScorePublisher::isFinal(UnknownRegistration::platform('i', 'c')));
        self::assertTrue(ScorePublisher::isFinal(InvalidRequest::because('x')));
        self::assertFalse(ScorePublisher::isFinal(TokenRequestRefused::invalidRequest('x')));
    }

    public function testTheBusTakingTheMessageIsRecorded(): void
    {
        $bus = $this->bus();
        $publisher = $this->publisher($bus);
        $id = $publisher->publish($this->launch(), $this->score());

        self::assertEquals($this->clock->now(), $this->pending->find($id)?->dispatchedAt);
        self::assertCount(1, $bus->messages);
        $this->clock->sleep(3600);
        self::assertSame(0, $publisher->recover(60), 'A score whose message was taken is not sent again.');
        self::assertCount(1, $bus->messages);
    }

    public function testAScoreWhoseMessageWasNeverTakenIsRecovered(): void
    {
        $bus = $this->bus();
        $publisher = $this->publisher($bus);
        $bus->failures = 1;
        $id = $publisher->publish($this->launch(), $this->score());

        self::assertSame(32, \strlen($id), 'A transport that refuses the message still gives the identifier of the score.');
        self::assertNull($this->pending->find($id)?->dispatchedAt);
        self::assertSame([], $bus->messages);

        $this->clock->sleep(59);
        self::assertSame(0, $publisher->recover(60), 'A score saved less than the delay ago may still be on its way.');
        $this->clock->sleep(1);
        $bus->failures = 1;
        self::assertSame(0, $publisher->recover(60), 'A transport that refuses again leaves the score for the next recovery.');
        self::assertNull($this->pending->find($id)?->dispatchedAt);
        self::assertSame(0, $publisher->recover(60), 'A score claimed waits the delay again.');
        $this->clock->sleep(60);
        self::assertSame(1, $publisher->recover(60));
        self::assertEquals([new PublishScore($id)], $bus->messages);
        self::assertEquals($this->clock->now(), $this->pending->find($id)?->dispatchedAt);
        self::assertSame(0, $publisher->recover(60));

        new PublishScoreHandler($publisher)(new PublishScore($id));
        self::assertNull($this->pending->find($id));
    }

    public function testARecoverySendsAtOnceWithoutABusAndStopsAtItsLimit(): void
    {
        $publisher = $this->publisher();

        foreach (['a', 'b', 'c'] as $id) {
            $this->pending->save(new PendingScore($id, 'https://lms.example.org', 'client', Url::fromString(self::ITEM), $this->score(), $this->clock->now()));
            $this->clock->sleep(1);
        }

        $this->clock->sleep(60);
        $this->status = 503;
        self::assertSame(0, $publisher->recover(60), 'A platform that still fails leaves the scores pending.');
        self::assertCount(3, $this->scoreRequests());
        self::assertNotNull($this->pending->find('a'));
        $this->clock->sleep(60);
        $this->status = 204;
        self::assertSame(2, $publisher->recover(60, 2));
        self::assertSame([null, null, false], [$this->pending->find('a'), $this->pending->find('b'), !$this->pending->find('c') instanceof PendingScore]);
        self::assertSame(1, $publisher->recover(60), 'The one the limit left out is sent by the next recovery.');
        self::assertNull($this->pending->find('c'));
        self::assertCount(6, $this->scoreRequests());
    }

    public function testTheCommandRecoversTheScoresWithinItsBounds(): void
    {
        $bus = $this->bus();
        $bus->failures = 2;

        $publisher = $this->publisher($bus);
        $publisher->publish($this->launch(), $this->score());
        $publisher->publish($this->launch(), $this->score());

        $command = new RecoverScoresCommand($publisher);
        $run = static function (int $delay, int $limit) use ($command): array {
            $output = new BufferedOutput();
            $status = $command(new SymfonyStyle(new ArrayInput([]), $output), $delay, $limit);

            return [$status, (string) preg_replace('/\s+/', ' ', $output->fetch())];
        };

        foreach ([[59, 1], [60, 0], [60, 1001]] as [$delay, $limit]) {
            self::assertSame([Command::INVALID, ' [ERROR] The delay is 60 seconds or more, and the limit 1 to 1,000. '], $run($delay, $limit), $delay.' '.$limit);
        }

        self::assertSame([Command::SUCCESS, ' [OK] Sent again 0 scores. '], $run(300, 100));
        $this->clock->sleep(60);
        self::assertSame([Command::SUCCESS, ' [OK] Sent again 1 scores. '], $run(60, 1));
        self::assertSame([Command::SUCCESS, ' [OK] Sent again 1 scores. '], $run(60, 1000));
        self::assertSame([Command::SUCCESS, ' [OK] Sent again 0 scores. '], $run(60, 1000));
        self::assertCount(2, $bus->messages);
    }

    public function testARecoveryWaitsASecondAtLeast(): void
    {
        try {
            $this->publisher()->recover(0);
            self::fail('A recovery without delay was made.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('A recovery waits a second at least: a score saved now may still be on its way.', $invalidArgumentException->getMessage());
        }
    }

    public function testAReasonIsKeptAsValidTextOfBoundedLength(): void
    {
        self::assertSame('abc', PendingScore::refusal('abc'));
        self::assertSame(str_repeat('a', 1023), PendingScore::refusal(str_repeat('a', 1023).'é'));
        self::assertSame(str_repeat('a', 1022).'é', PendingScore::refusal(str_repeat('a', 1022).'éé'));
        self::assertSame('a?b', PendingScore::refusal("a\xFFb"));
    }

    public function testARequestThatCannotBeSentIsDroppedWithoutRetry(): void
    {
        $publisher = $this->publisher(new class implements MessageBusInterface {
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                return new Envelope($message);
            }
        });
        $id = $publisher->publish($this->launch(), $this->score());
        $this->failure = InvalidRequest::because('Refused.');

        try {
            new PublishScoreHandler($publisher)(new PublishScore($id));
            self::fail('A request that cannot be sent was tried again.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertInstanceOf(InvalidRequest::class, $unrecoverableMessageHandlingException->getPrevious());
            self::assertSame(0, $unrecoverableMessageHandlingException->getCode());
        }
    }

    public function testTheMemoryStoreForgetsOnReset(): void
    {
        $this->pending->save(new PendingScore('p', 'i', 'c', Url::fromString(self::ITEM), $this->score(), $this->clock->now()));
        $this->pending->reset();

        self::assertNull($this->pending->find('p'));
    }

    private function bus(): RecordingBus
    {
        return new RecordingBus();
    }

    private function publisher(?MessageBusInterface $bus = null, ?EventDispatcher $events = null): ScorePublisher
    {
        $client = new readonly class($this) implements ServiceClient {
            public function __construct(
                private ScorePublisherTest $test,
            ) {
            }

            public function send(ServiceRequest $request): ServiceResponse
            {
                return $this->test->answer($request);
            }
        };

        $keys = new InMemoryKeyStore(RsaPrivateKey::fromPem(TestKeys::pem(), 'k'));
        $ags = new AgsClient(new ServiceCall($client, new AccessTokenProvider($client, $keys, new ArrayAdapter(), $this->clock)));

        return new ScorePublisher($this->pending, $this->platforms, $ags, $this->clock, $bus, $events);
    }

    /**
     * @internal for the client of the test
     */
    public function answer(ServiceRequest $request): ServiceResponse
    {
        $this->requests[] = $request;

        if ('https://lms.example.org/token' === $request->url->toString()) {
            return new ServiceResponse(200, [], '{"access_token":"t","token_type":"Bearer","expires_in":3600}');
        }

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return new ServiceResponse($this->status, [], '');
    }

    /**
     * @return list<ServiceRequest>
     */
    private function scoreRequests(): array
    {
        return array_values(array_filter($this->requests, static fn (ServiceRequest $request): bool => str_ends_with($request->url->path, '/scores')));
    }

    private function launch(AgsEndpoint|false|null $endpoint = false, string $issuer = 'https://lms.example.org'): Launch
    {
        $endpoint = false === $endpoint ? new AgsEndpoint([Scope::SCORE], null, Url::fromString(self::ITEM)) : $endpoint;

        return new Launch($issuer, 'client', new LaunchMessage(MessageType::ResourceLink, 'd', Url::fromString('https://tool.example.com/'), new Person('u'), Roles::of([]), new ResourceLink('l'), ags: $endpoint), []);
    }

    private function score(): Score
    {
        return new Score('u', ActivityProgress::Completed, GradingProgress::FullyGraded, new DateTimeImmutable('2026-11-14T12:00:00+00:00'), 1.0, 1.0);
    }
}
