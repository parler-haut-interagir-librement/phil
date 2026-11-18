<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Xapi;

use ArrayObject;
use InvalidArgumentException;
use Override;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Exception\RecordStoreUnavailable;
use Phil\CoursesBundle\Infrastructure\Xapi\HttpLearningRecordStore;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\SafeHttp\Exception\FetchFailed;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\Url\Exception\InvalidUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * The remote learning record store: each statement put at its identifier with the xAPI version, as JSON, with HTTP
 * Basic authentication; a statement the store has already counted kept; an error or a store out of reach translated;
 * the state documents kept local, in the store inside the bundle; the endpoint, the credentials and the version
 * checked; and a log that names no learner, registration, statement body nor secret.
 */
#[CoversClass(HttpLearningRecordStore::class)]
#[CoversClass(RecordStoreUnavailable::class)]
final class HttpLearningRecordStoreTest extends TestCase
{
    private const string ID = 'a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a';

    private const string REGISTRATION = '0b4f9a2e-6c1d-4f3e-8a5b-9c7d2e1f0a3b';

    private const string BODY = '{"id":"a7d1c6b8-0e0f-4c3a-9a37-2b8f1d4e5c6a","actor":{"account":{"name":"ada"}}}';

    /** @var ArrayObject<int, array{method: string, url: string, headers: list<string>, body: string}> */
    private ArrayObject $sent;

    /** @var ArrayObject<int, array{string, string, array<array-key, mixed>}> */
    private ArrayObject $logs;

    #[Override]
    protected function setUp(): void
    {
        $this->sent = new ArrayObject();
        $this->logs = new ArrayObject();
    }

    public function testAStatementIsPutAtItsIdentifierWithTheVersionAndTheCredentials(): void
    {
        $store = $this->store([new MockResponse('', ['http_code' => 204])], username: 'key', password: 'secret', version: '2.0.0');
        $store->store(self::ID, self::BODY, self::REGISTRATION);

        $request = $this->sent(0);
        self::assertSame(['PUT', 'https://lrs.example.com/xapi/statements?statementId='.self::ID, self::BODY], [$request['method'], $request['url'], $request['body']]);
        self::assertContains('X-Experience-API-Version: 2.0.0', $request['headers']);
        self::assertContains('Content-Type: application/json', $request['headers']);
        self::assertContains('Accept: application/json', $request['headers']);
        self::assertContains('Authorization: Basic '.base64_encode('key:secret'), $request['headers']);
        self::assertSame([], $this->logs->getArrayCopy());
    }

    public function testWithoutCredentialsNoAuthorizationIsSentAndTheEndpointIsJoinedOnce(): void
    {
        $store = $this->store([new MockResponse('', ['http_code' => 200])], endpoint: 'https://lrs.example.com/xapi');
        $store->store('id with/slash', self::BODY, self::REGISTRATION);

        self::assertSame('https://lrs.example.com/xapi/statements?statementId=id%20with%2Fslash', $this->sent(0)['url']);
        self::assertContains('X-Experience-API-Version: 1.0.3', $this->sent(0)['headers']);
        self::assertSame([], array_filter($this->sent(0)['headers'], static fn (string $header): bool => str_starts_with($header, 'Authorization')));
    }

    public function testAStatementTheStoreHasAlreadyIsKept(): void
    {
        $this->store([new MockResponse('', ['http_code' => 409])])->store(self::ID, self::BODY, self::REGISTRATION);

        self::assertSame([['info', 'The learning record store has the statement {statement} already.', ['statement' => self::ID]]], $this->logs->getArrayCopy());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function errors(): iterable
    {
        yield 'unauthorized' => [401];
        yield 'bad request' => [400];
        yield 'server error' => [500];
        yield 'redirect' => [302];
        yield 'informational' => [199];
    }

    #[DataProvider('errors')]
    public function testAnErrorOfTheStoreIsTranslated(int $status): void
    {
        $store = $this->store([new MockResponse('{"message":"ada is not allowed"}', ['http_code' => $status, 'response_headers' => ['Content-Type' => 'application/json']])]);

        try {
            $store->store(self::ID, self::BODY, self::REGISTRATION);
            self::fail('The error is hidden.');
        } catch (RecordStoreUnavailable $recordStoreUnavailable) {
            self::assertInstanceOf(CoursesException::class, $recordStoreUnavailable);
            self::assertSame(\sprintf('The learning record store did not keep the statement: it answered HTTP %d.', $status), $recordStoreUnavailable->getMessage());
        }

        self::assertSame([['warning', 'The learning record store refused the statement {statement}: HTTP {status}.', ['statement' => self::ID, 'status' => $status]]], $this->logs->getArrayCopy());
        self::assertStringNotContainsString('ada', (string) json_encode($this->logs->getArrayCopy()));
    }

    public function testAStoreOutOfReachIsTranslated(): void
    {
        $store = $this->store([new MockResponse('', ['error' => 'Connection refused'])]);

        try {
            $store->store(self::ID, self::BODY, self::REGISTRATION);
            self::fail('The failure is hidden.');
        } catch (RecordStoreUnavailable $recordStoreUnavailable) {
            self::assertSame('The learning record store did not keep the statement: it could not be reached within the limits.', $recordStoreUnavailable->getMessage());
            self::assertInstanceOf(FetchFailed::class, $recordStoreUnavailable->getPrevious());
            self::assertSame(0, $recordStoreUnavailable->getCode());
        }

        self::assertSame([['warning', 'The learning record store could not be reached for the statement {statement}: {failure}.', ['statement' => self::ID, 'failure' => FetchFailed::class]]], $this->logs->getArrayCopy());
        self::assertStringNotContainsString(self::REGISTRATION, (string) json_encode($this->logs->getArrayCopy()));
    }

    public function testWithoutALoggerAFailureStillThrows(): void
    {
        $store = new HttpLearningRecordStore($this->client([new MockResponse('', ['http_code' => 409]), new MockResponse('', ['http_code' => 500]), new MockResponse('', ['error' => 'refused'])]), 'https://lrs.example.com/', new Store()->lrs);
        $store->store(self::ID, self::BODY, self::REGISTRATION);

        foreach (['HTTP 500', 'could not be reached'] as $message) {
            try {
                $store->store(self::ID, self::BODY, self::REGISTRATION);
                self::fail('The failure is hidden.');
            } catch (RecordStoreUnavailable $recordStoreUnavailable) {
                self::assertStringContainsString($message, $recordStoreUnavailable->getMessage());
            }
        }
    }

    public function testTheStateDocumentsStayLocalAndAreNeverSent(): void
    {
        $inner = new Store();
        $store = $this->store([], inner: $inner);

        $store->saveState('https://learn.example.org/a', self::REGISTRATION, 'suspend', '{"page":1}');
        self::assertSame('{"page":1}', $store->state('https://learn.example.org/a', self::REGISTRATION, 'suspend'));
        self::assertSame('{"page":1}', $inner->lrs->state('https://learn.example.org/a', self::REGISTRATION, 'suspend'));
        $store->deleteState('https://learn.example.org/a', self::REGISTRATION, 'suspend');
        self::assertNull($inner->lrs->state('https://learn.example.org/a', self::REGISTRATION, 'suspend'));
        self::assertCount(0, $this->sent);
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, string, string}>
     */
    public static function wrongSettings(): iterable
    {
        yield 'not an address' => ['lrs', null, null, '1.0.3', 'The endpoint of the learning record store is not an address.'];
        yield 'clear text' => ['http://lrs.example.com/', null, null, '1.0.3', 'an https address without a query or a fragment'];
        yield 'query' => ['https://lrs.example.com/?tenant=1', null, null, '1.0.3', 'an https address without a query or a fragment'];
        yield 'fragment' => ['https://lrs.example.com/#x', null, null, '1.0.3', 'an https address without a query or a fragment'];
        yield 'version' => ['https://lrs.example.com/', null, null, '1.0.2', 'The xAPI version is one of 1.0.3, 2.0.0.'];
        yield 'user name alone' => ['https://lrs.example.com/', 'key', null, '1.0.3', 'a user name without ":" and a password, or neither'];
        yield 'password alone' => ['https://lrs.example.com/', null, 'secret', '1.0.3', 'a user name without ":" and a password, or neither'];
        yield 'colon' => ['https://lrs.example.com/', 'k:ey', 'secret', '1.0.3', 'a user name without ":" and a password, or neither'];
    }

    #[DataProvider('wrongSettings')]
    public function testAWrongSettingIsRefused(string $endpoint, ?string $username, ?string $password, string $version, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->store([], endpoint: $endpoint, username: $username, password: $password, version: $version);
    }

    public function testAnEndpointThatIsNotAnAddressKeepsItsCauseAndHasNoCode(): void
    {
        try {
            $this->store([], endpoint: 'lrs');
            self::fail('The endpoint is accepted.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(0, $invalidArgumentException->getCode());
            self::assertInstanceOf(InvalidUrl::class, $invalidArgumentException->getPrevious());
        }
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function store(array $responses, string $endpoint = 'https://lrs.example.com/xapi/', ?string $username = null, ?string $password = null, string $version = '1.0.3', ?Store $inner = null): HttpLearningRecordStore
    {
        $this->logs = new ArrayObject();
        $logs = $this->logs;
        $logger = new class($logs) extends AbstractLogger {
            /**
             * @param ArrayObject<int, array{string, string, array<array-key, mixed>}> $logs
             */
            public function __construct(
                private readonly ArrayObject $logs,
            ) {
            }

            /**
             * @param array<array-key, mixed> $context
             */
            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->logs->append([\is_string($level) ? $level : '', (string) $message, $context]);
            }
        };

        return new HttpLearningRecordStore($this->client($responses), $endpoint, ($inner ?? new Store())->lrs, $username, $password, $version, $logger);
    }

    /**
     * @return array{method: string, url: string, headers: list<string>, body: string}
     */
    private function sent(int $request): array
    {
        $sent = $this->sent[$request] ?? null;
        self::assertNotNull($sent);

        return $sent;
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function client(array $responses): SafeHttpClient
    {
        $this->sent = new ArrayObject();
        $sent = $this->sent;

        return new SafeHttpClient(new MockHttpClient(static function (string $method, string $url, array $options) use ($sent, &$responses): MockResponse {
            $headers = $options['headers'] ?? [];
            $body = $options['body'] ?? '';
            $sent->append([
                'method' => $method,
                'url' => $url,
                'headers' => \is_array($headers) ? array_values(array_filter($headers, \is_string(...))) : [],
                'body' => \is_string($body) ? $body : '',
            ]);

            return array_shift($responses) ?? new MockResponse('');
        }), new Limits(maxRedirects: 0), ['lrs.example.com' => '93.184.215.14']);
    }
}
