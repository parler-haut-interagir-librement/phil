<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Lti\Contract\ServiceClient;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\ServiceFailed;
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
use Phil\LtiBundle\Application\Message\PublishScore;
use Phil\LtiBundle\Application\PendingScore;
use Phil\LtiBundle\Application\ScorePublisher;
use Phil\LtiBundle\Infrastructure\Memory\InMemoryPendingScores;
use Phil\LtiBundle\Infrastructure\Messenger\PublishScoreHandler;
use Phil\LtiBundle\Tests\Unit\Fixture\TestKeys;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;

#[CoversClass(ScorePublisher::class)]
#[CoversClass(PendingScore::class)]
#[CoversClass(PublishScore::class)]
#[CoversClass(ScorePublished::class)]
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

        self::assertNull($this->pending->find($id));

        try {
            $publisher->send($publisher->publish($this->launch(issuer: 'https://gone.example.org'), $this->score()));
            self::fail('A score of an unknown platform was sent.');
        } catch (UnknownRegistration) {
            self::addToAssertionCount(1);
        }
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
