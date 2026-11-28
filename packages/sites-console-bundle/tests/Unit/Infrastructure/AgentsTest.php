<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Infrastructure;

use Closure;
use LogicException;
use Override;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Phil\SitesConsoleBundle\Application\Agent\AgentEndpoints;
use Phil\SitesConsoleBundle\Application\Agent\ConsoleKeyRing;
use Phil\SitesConsoleBundle\Application\Agent\RequestVerifier;
use Phil\SitesConsoleBundle\Application\Console\ConsoleSigning;
use Phil\SitesConsoleBundle\Application\Exception\AgentFailure;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Signature\Protocol;
use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\Status\KeyOperation;
use Phil\SitesConsoleBundle\Domain\ValueObject\AgentUrl;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\KeyRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;
use Phil\SitesConsoleBundle\Infrastructure\Cache\CacheConsoleKeyStore;
use Phil\SitesConsoleBundle\Infrastructure\Cache\CacheNonceStore;
use Phil\SitesConsoleBundle\Infrastructure\Http\AgentController;
use Phil\SitesConsoleBundle\Infrastructure\Http\HttpAgentClient;
use Phil\SitesConsoleBundle\Infrastructure\Monitoring\MonitoringSiteHealth;
use Phil\SitesConsoleBundle\Infrastructure\Monitoring\UnknownSiteHealth;
use Phil\SitesConsoleBundle\Tests\Unit\Support\FakeMonitoring;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Memory;
use Phil\SitesConsoleBundle\Tests\Unit\Support\RecordingLogger;
use Phil\SitesConsoleBundle\Tests\Unit\Support\RefusingPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * The console's HTTP client against the agent's controller, through a mock transport that hands each request to the
 * controller as a Symfony request: the whole signed exchange, both ways.
 */
#[CoversClass(HttpAgentClient::class)]
#[CoversClass(AgentController::class)]
#[CoversClass(MonitoringSiteHealth::class)]
#[CoversClass(UnknownSiteHealth::class)]
final class AgentsTest extends TestCase
{
    private MockClock $clock;

    private Memory $memory;

    private IdempotencyGuard $guard;

    private RecordingLogger $logger;

    private ConsoleKeyRing $ring;

    /** @var list<array{string, string, array<array-key, mixed>}> */
    private array $requests = [];

    protected function setUp(): void
    {
        $this->clock = new MockClock(Fixtures::NOW);
        $this->logger = new RecordingLogger();
        $this->memory = new Memory(['phil/url' => '0.1.4']);
        $this->guard = new IdempotencyGuard(new CacheIdempotencyStore(new ArrayAdapter(clock: $this->clock), new LockFactory(new InMemoryStore()), $this->clock), $this->clock);
        $this->ring = new ConsoleKeyRing(Keys::console()->verifyingKey(), null, new CacheConsoleKeyStore(new ArrayAdapter(clock: $this->clock)));
    }

    public function testTheConsoleReadsTheStatusOfTheSite(): void
    {
        $status = $this->client()->status(Fixtures::site());

        self::assertSame('shop', $status->site);
        self::assertSame(['phil/url' => '0.1.4'], $status->packages);
        [$method, $url, $options] = $this->requests[0];
        self::assertSame('GET', $method);
        self::assertSame('https://shop.example.com/_sites-console/status', $url);
        self::assertSame(0, $options['max_redirects']);
        self::assertEqualsWithDelta(10.0, $options['timeout'], 0.001);
        self::assertEqualsWithDelta(10.0, $options['max_duration'], 0.001);
        self::assertContains('Accept: application/json', self::headers($options));
        self::assertContains('Phil-Console-Site: shop', self::headers($options));
        self::assertContains('Phil-Console-Date: '.Fixtures::now()->getTimestamp(), self::headers($options));
    }

    public function testThePushAndTheUpdateArePostedAndAnsweredOnce(): void
    {
        $client = $this->client(keys: ['mail.*'], runner: true);
        $site = Fixtures::site();
        $request = new ConfigurationRequest('PUSH1', Settings::of(['mail.footer' => 'Hi']));

        self::assertSame('1 settings applied.', $client->configure($site, $request)->message);
        self::assertTrue($client->configure($site, $request)->replayed);
        self::assertSame('POST', $this->requests[0][0]);
        self::assertSame('{"id":"PUSH1","settings":{"mail.footer":"Hi"}}', $this->requests[0][2]['body']);

        $update = $client->update($site, new UpdateRequest('PLAN:shop', 'phil/url', '0.1.5'));
        self::assertSame('Deployed 0.1.5', $update->message);
        self::assertSame('https://shop.example.com/_sites-console/update', $this->requests[2][1]);
        self::assertEqualsWithDelta(600.0, $this->requests[2][2]['timeout'], 0.001);
    }

    public function testErrorsOfTheAgentAreSignedAndReported(): void
    {
        $site = Fixtures::site();

        $this->assertFailure('The agent answered 501: This site applies no pushed configuration.', fn (): Outcome => $this->client()->configure($site, new ConfigurationRequest('P', Settings::of(['a' => 1]))));
        $this->assertFailure('The agent answered 422: This site does not accept the keys a.', fn (): Outcome => $this->client(keys: ['b'])->configure($site, new ConfigurationRequest('P', Settings::of(['a' => 1]))));
        $this->assertFailure('The agent answered 501: This site runs no update.', fn (): Outcome => $this->client()->update($site, new UpdateRequest('P', 'phil/url', '1')));
    }

    public function testAReusedKeyAndARunInProgress(): void
    {
        $site = Fixtures::site();
        $client = $this->client(keys: ['*']);
        $client->configure($site, new ConfigurationRequest('P', Settings::of(['a' => 1])));

        $this->assertFailure('The agent answered 422: This idempotency key was used with another request.', static fn (): Outcome => $client->configure($site, new ConfigurationRequest('P', Settings::of(['a' => 2]))));

        $body = new ConfigurationRequest('Q', Settings::of(['a' => 1]))->toJson();
        $this->guard->begin(IdempotencyScope::of(AgentEndpoints::SCOPE_CONFIGURATION), IdempotencyKey::fromString('Q'), PayloadFingerprint::ofParts(AgentEndpoints::SCOPE_CONFIGURATION, $body));
        $this->assertFailure('The agent answered 409: The same request is still running.', static fn (): Outcome => $client->configure($site, new ConfigurationRequest('Q', Settings::of(['a' => 1]))));
        self::assertSame('300', $this->lastResponse?->headers->get('Retry-After'));
    }

    public function testAMalformedBodyIsABadRequest(): void
    {
        $controller = $this->controller(['*'], true);
        $response = $controller->configuration($this->signed('configuration', '{"id":"P"}'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('{"error":"The settings are an object of keys and values."}', $response->getContent());
        self::assertNotNull($response->headers->get(Protocol::HEADER_AGENT_SIGNATURE));

        $badKey = $controller->update($this->signed('update', '{"id":" x","package":"phil/url","version":"1"}'));
        self::assertSame(400, $badKey->getStatusCode());
    }

    public function testAnUnsignedRequestIsRefusedUnsignedAndLogged(): void
    {
        $response = $this->controller()->status(Request::create('/status'));

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('{"error":"The request is not signed by the console."}', $response->getContent());
        self::assertNull($response->headers->get(Protocol::HEADER_AGENT_SIGNATURE));
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame(['warning: A request to the agent was refused: A header of the signature is missing.'], $this->logger->lines);

        $this->assertFailure('The agent answered 401 without a valid signature.', fn (): StatusDocument => $this->client(consoleKey: Keys::site())->status(Fixtures::site()));
    }

    public function testATooLargeBodyIsRefusedBeforeAnything(): void
    {
        $body = str_repeat('a', AgentController::MAX_BODY_BYTES + 1);
        $response = $this->controller()->update($this->signed('update', $body));

        self::assertSame(413, $response->getStatusCode());
        self::assertSame('{"error":"The request is too large."}', $response->getContent());
        self::assertNull($response->headers->get(Protocol::HEADER_AGENT_SIGNATURE));
        self::assertSame([], $this->memory->nonces);
        self::assertSame(400, $this->controller([], true)->update($this->signed('update', str_repeat(' ', AgentController::MAX_BODY_BYTES)))->getStatusCode());
    }

    public function testABodyPastTheCapIsRefusedWhateverItsLengthSays(): void
    {
        $body = str_repeat('a', AgentController::MAX_BODY_BYTES + 1);
        $lying = $this->signed('update', $body);
        $lying->headers->set('Content-Length', '10');

        $declared = $this->signed('update', '{}');
        $declared->headers->set('Content-Length', (string) (AgentController::MAX_BODY_BYTES + 1));

        self::assertSame(413, $this->controller()->update($lying)->getStatusCode(), 'A length that lies.');
        self::assertSame(413, $this->controller()->update($declared)->getStatusCode(), 'A length past the cap, before reading.');
        self::assertSame([], $this->memory->nonces);
    }

    public function testANonceTheSiteCannotKeepIsUnavailableAndUnsigned(): void
    {
        $verifier = new RequestVerifier('shop', $this->ring, new CacheNonceStore(new RefusingPool(), new LockFactory(new InMemoryStore())), $this->clock);
        $endpoints = new AgentEndpoints('shop', $this->memory, $this->memory, $this->guard, $this->clock);
        $response = new AgentController($verifier, $endpoints, Keys::site(), 'shop', $this->logger)->status($this->signed('status', ''));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('{"error":"The site cannot accept the request now."}', $response->getContent());
        self::assertSame('60', $response->headers->get('Retry-After'));
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertNull($response->headers->get(Protocol::HEADER_AGENT_SIGNATURE));
        self::assertSame(['error: A request to the agent was not accepted: The nonce of the request could not be kept.'], $this->logger->lines);

        $quiet = new AgentController($verifier, $endpoints, Keys::site(), 'shop')->status($this->signed('status', ''));
        self::assertSame(503, $quiet->getStatusCode(), 'Without a logger too.');
    }

    public function testARefusalIsAnsweredWithoutALogger(): void
    {
        $verifier = new RequestVerifier('shop', $this->ring, $this->memory, $this->clock);
        $endpoints = new AgentEndpoints('shop', $this->memory, $this->memory, $this->guard, $this->clock);

        self::assertSame(401, new AgentController($verifier, $endpoints, Keys::site(), 'shop')->status(Request::create('/status'))->getStatusCode());
    }

    public function testTheRequestIsSentWithItsRawDateAndItsDownloadIsBounded(): void
    {
        $http = new class implements HttpClientInterface {
            /** @var array<array-key, mixed> */
            public array $options = [];

            /**
             * @param array<array-key, mixed> $options
             */
            #[Override]
            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                $this->options = $options;

                return new MockResponse('{}');
            }

            #[Override]
            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                throw new LogicException('Not streamed.');
            }

            /**
             * @param array<array-key, mixed> $options
             */
            #[Override]
            public function withOptions(array $options): static
            {
                return $this;
            }
        };

        try {
            new HttpAgentClient($http, new ConsoleSigning(Keys::console()), $this->clock, maxBytes: 10)->status(Fixtures::site());
        } catch (AgentFailure) {
        }

        self::assertIsArray($http->options['headers']);
        self::assertSame((string) Fixtures::now()->getTimestamp(), $http->options['headers'][Protocol::HEADER_DATE]);
        $progress = $http->options['on_progress'];
        self::assertIsCallable($progress);
        $progress(10, -1, []);

        $this->assertFailure('The agent answered a malformed document: the answer is larger than 10 bytes.', static fn (): mixed => $progress(11, -1, []));
    }

    public function testAnAnswerSignedByAnotherKeyIsRefused(): void
    {
        $site = Site::register('shop', 'Shop', AgentUrl::fromString('https://shop.example.com/_sites-console'), Keys::console()->verifyingKey(), [], Fixtures::now());

        $this->assertFailure('The agent answered 200 without a valid signature.', fn (): StatusDocument => $this->client()->status($site));
    }

    public function testAnAnswerWithoutSignatureOrForAnotherRequestIsRefused(): void
    {
        $unsigned = new HttpAgentClient(new MockHttpClient(new MockResponse('{}')), new ConsoleSigning(Keys::console()), $this->clock);
        $this->assertFailure('The agent answered 200 without a valid signature.', static fn (): StatusDocument => $unsigned->status(Fixtures::site()));

        $replayed = new HttpAgentClient(new MockHttpClient(new MockResponse('{}', ['response_headers' => [Protocol::HEADER_AGENT_SIGNATURE => Keys::site()->sign(Protocol::responseMessage('shop', str_repeat('n', 32), 200, '{}'))]])), new ConsoleSigning(Keys::console()), $this->clock);
        $this->assertFailure('The agent answered 200 without a valid signature.', static fn (): StatusDocument => $replayed->status(Fixtures::site()));
    }

    public function testMalformedAnswersOfTheAgent(): void
    {
        $this->assertFailure('The agent answered a malformed document: "document" is an object.', fn (): StatusDocument => $this->signedClient('[1]')->status(Fixtures::site()));
        $this->assertFailure('The agent answered a malformed document: "outcome" is succeeded or failed, and "replayed" a boolean.', fn (): Outcome => $this->signedClient('{"outcome":"x"}')->configure(Fixtures::site(), new ConfigurationRequest('P', Settings::of(['a' => 1]))));
        $this->assertFailure('The agent answered 500.', fn (): StatusDocument => $this->signedClient('oops', 500)->status(Fixtures::site()));
        $this->assertFailure('The agent answered 500.', fn (): StatusDocument => $this->signedClient('{"error":null}', 500)->status(Fixtures::site()));
    }

    public function testAnAnswerTooLargeIsRefused(): void
    {
        $this->assertFailure('The agent answered a malformed document: the answer is larger than 10 bytes.', fn (): Outcome => $this->signedClient('{"outcome":"succeeded"}', maxBytes: 10)->update(Fixtures::site(), new UpdateRequest('P', 'phil/url', '1')));
        self::assertSame('ok', $this->signedClient('{"outcome":"succeeded","message":"ok"}', maxBytes: 38)->update(Fixtures::site(), new UpdateRequest('P', 'phil/url', '1'))->message);
    }

    public function testATransportFailureIsUnreachable(): void
    {
        $client = new HttpAgentClient(new MockHttpClient(static fn (): ResponseInterface => throw new TransportException('Connection refused')), new ConsoleSigning(Keys::console()), $this->clock);

        $this->assertFailure('The agent could not be reached: Connection refused', static fn (): StatusDocument => $client->status(Fixtures::site()));
    }

    public function testTheProgressOfADownloadIsBounded(): void
    {
        $client = new HttpAgentClient(new MockHttpClient(new MockResponse([str_repeat('a', 6), str_repeat('a', 6)])), new ConsoleSigning(Keys::console()), $this->clock, maxBytes: 10);

        try {
            $client->status(Fixtures::site());
            self::fail('The download stops.');
        } catch (AgentFailure $agentFailure) {
            self::assertStringContainsString('larger than 10 bytes', $agentFailure->getMessage());
        }
    }

    public function testTheConsoleKeyRotatesOverTheSignedExchange(): void
    {
        $site = Fixtures::site();
        $next = Keys::next()->verifyingKey();

        $this->assertFailure('The agent answered 401 without a valid signature.', fn (): StatusDocument => $this->client(consoleKey: Keys::next())->status($site));

        $staged = $this->client()->keys($site, new KeyRequest('R:stage:shop', KeyOperation::Stage, $next));
        self::assertSame('The next key is staged.', $staged->message);
        self::assertSame('{"id":"R:stage:shop","operation":"stage","key":"'.$next->toBase64().'"}', $this->requests[1][2]['body']);
        self::assertSame('https://shop.example.com/_sites-console/keys', $this->requests[1][1]);
        self::assertEqualsWithDelta(10.0, $this->requests[1][2]['timeout'], 0.001);
        self::assertTrue($this->client()->keys($site, new KeyRequest('R:stage:shop', KeyOperation::Stage, $next))->replayed);

        self::assertSame('shop', $this->client()->status($site)->site, 'The current key still verifies.');
        self::assertSame('shop', $this->client(consoleKey: Keys::next())->status($site)->site, 'The next key verifies too.');

        $this->assertFailure('The agent answered 422: A key is retired by a request signed with the key that remains.', fn (): Outcome => $this->client()->keys($site, new KeyRequest('R:retire:shop', KeyOperation::Retire, Keys::console()->verifyingKey())));
        self::assertSame('The key is retired.', $this->client(consoleKey: Keys::next())->keys($site, new KeyRequest('R:retire:shop', KeyOperation::Retire, Keys::console()->verifyingKey()))->message);

        $this->assertFailure('The agent answered 401 without a valid signature.', fn (): StatusDocument => $this->client()->status($site));
        self::assertSame('shop', $this->client(consoleKey: Keys::next())->status($site)->site);
        $this->assertFailure('The agent answered 422: The only key of the console cannot be retired.', fn (): Outcome => $this->client(consoleKey: Keys::next())->keys($site, new KeyRequest('R2', KeyOperation::Retire, $next)));
    }

    public function testAnAgentWithoutAKeyRingTakesNoKey(): void
    {
        $verifier = new RequestVerifier('shop', $this->ring, $this->memory, $this->clock);
        $endpoints = new AgentEndpoints('shop', $this->memory, $this->memory, $this->guard, $this->clock);
        $response = new AgentController($verifier, $endpoints, Keys::site(), 'shop')->keys($this->signed('keys', new KeyRequest('K', KeyOperation::Stage, Keys::next()->verifyingKey())->toJson()));

        self::assertSame(501, $response->getStatusCode());
        self::assertSame('{"error":"This site takes no console key."}', $response->getContent());
    }

    public function testTheHealthFromMonitoring(): void
    {
        $report = new HealthReport([new CheckResult('database', CheckOutcome::down('Connection refused'), 3), new CheckResult('cache', CheckOutcome::up(), 1)], Fixtures::now());
        $summary = new MonitoringSiteHealth(new FakeMonitoring($report))->summary();

        self::assertSame(HealthState::Down, $summary->state);
        self::assertSame([['name' => 'database', 'status' => 'down', 'summary' => 'Connection refused'], ['name' => 'cache', 'status' => 'up', 'summary' => '']], $summary->toArray()['checks']);
        self::assertSame(HealthState::Unknown, new UnknownSiteHealth()->summary()->state);
    }

    private ?Response $lastResponse = null;

    /**
     * @param list<string> $keys
     */
    private function client(array $keys = [], ?SigningKey $consoleKey = null, bool $runner = false): HttpAgentClient
    {
        $controller = $this->controller($keys, $runner);
        $transport = new MockHttpClient(function (string $method, string $url, array $options) use ($controller): MockResponse {
            $this->requests[] = [$method, $url, $options];
            $request = Request::create($url, $method, server: [], content: \is_string($options['body'] ?? null) ? $options['body'] : '');

            foreach (self::headers($options) as $header) {
                [$name, $value] = explode(': ', $header, 2) + [1 => ''];
                $request->headers->set($name, $value);
            }

            $action = basename($url);
            $response = match ($action) {
                'status' => $controller->status($request),
                'configuration' => $controller->configuration($request),
                'keys' => $controller->keys($request),
                default => $controller->update($request),
            };
            $this->lastResponse = $response;

            return new MockResponse((string) $response->getContent(), ['http_code' => $response->getStatusCode(), 'response_headers' => $response->headers->allPreserveCase()]);
        });

        return new HttpAgentClient($transport, new ConsoleSigning($consoleKey ?? Keys::console()), $this->clock);
    }

    private function signedClient(string $body, int $status = 200, int $maxBytes = 1_048_576): HttpAgentClient
    {
        $transport = new MockHttpClient(static function (string $method, string $url, array $options) use ($body, $status): MockResponse {
            $nonce = '';

            foreach (self::headers($options) as $header) {
                if (str_starts_with($header, Protocol::HEADER_NONCE.': ')) {
                    $nonce = substr($header, \strlen(Protocol::HEADER_NONCE) + 2);
                }
            }

            return new MockResponse($body, ['http_code' => $status, 'response_headers' => [Protocol::HEADER_AGENT_SIGNATURE => Keys::site()->sign(Protocol::responseMessage('shop', $nonce, $status, $body))]]);
        });

        return new HttpAgentClient($transport, new ConsoleSigning(Keys::console()), $this->clock, 10.0, 600.0, $maxBytes);
    }

    /**
     * @param list<string> $keys
     */
    private function controller(array $keys = [], bool $runner = false): AgentController
    {
        $verifier = new RequestVerifier('shop', $this->ring, $this->memory, $this->clock);
        $endpoints = new AgentEndpoints('shop', $this->memory, $this->memory, $this->guard, $this->clock, [], $keys, [] === $keys ? null : $this->memory, $runner ? $this->memory : null, null, $this->ring);

        return new AgentController($verifier, $endpoints, Keys::site(), 'shop', $this->logger);
    }

    /**
     * The header lines of the options the mock transport gets.
     *
     * @param array<array-key, mixed> $options
     *
     * @return list<string>
     */
    private static function headers(array $options): array
    {
        $headers = \is_array($options['headers'] ?? null) ? $options['headers'] : [];

        return array_values(array_filter($headers, \is_string(...)));
    }

    private function signed(string $action, string $body): Request
    {
        $nonce = Protocol::nonce();
        $date = Fixtures::now()->getTimestamp();
        $request = Request::create('/'.$action, 'POST', content: $body);
        $request->headers->add([
            Protocol::HEADER_SITE => 'shop',
            Protocol::HEADER_DATE => (string) $date,
            Protocol::HEADER_NONCE => $nonce,
            Protocol::HEADER_SIGNATURE => Keys::console()->sign(Protocol::requestMessage('shop', $action, $date, $nonce, $body)),
        ]);

        return $request;
    }

    /**
     * @param Closure(): mixed $call
     */
    private function assertFailure(string $message, Closure $call): void
    {
        try {
            $call();
            self::fail($message);
        } catch (AgentFailure $agentFailure) {
            self::assertSame($message, $agentFailure->getMessage());
        }
    }
}
