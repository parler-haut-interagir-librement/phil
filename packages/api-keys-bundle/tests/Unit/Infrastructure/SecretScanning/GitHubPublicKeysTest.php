<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure\SecretScanning;

use Generator;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\Exception\UnreadableReport;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\GitHubPublicKeys;
use Phil\ApiKeysBundle\Tests\Unit\Fixture\GitHubKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

#[CoversClass(GitHubPublicKeys::class)]
final class GitHubPublicKeysTest extends TestCase
{
    public function testTheKeysAreFetchedOnceAndKept(): void
    {
        $keys = new GitHubKeys();
        $other = new GitHubKeys('other');
        $response = new MockResponse($keys->document([$other]));
        $http = new MockHttpClient($response);
        $cache = new ArrayAdapter();
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $github = new GitHubPublicKeys($http, $cache, $clock, 'https://keys.example.com/', 3600, 60, 7);

        self::assertSame($keys->publicPem, $github->key($keys->identifier));
        self::assertSame($other->publicPem, $github->key('other'));
        self::assertSame(1, $http->getRequestsCount());
        self::assertSame(['GET', 'https://keys.example.com/'], [$response->getRequestMethod(), $response->getRequestUrl()]);
        $options = $response->getRequestOptions();
        self::assertSame([7.0, 7.0, 0], [$options['timeout'] ?? null, $options['max_duration'] ?? null, $options['max_redirects'] ?? null]);
        self::assertSame(['Accept: application/json'], $options['headers'] ?? null);

        $item = $cache->getItem(GitHubPublicKeys::CACHE_KEY);
        self::assertTrue($item->isHit());
        self::assertSame(['fetched_at' => $clock->now()->getTimestamp(), 'keys' => [$keys->identifier => $keys->publicPem, 'other' => $other->publicPem]], $item->get());
    }

    public function testTheDefaults(): void
    {
        $keys = new GitHubKeys();
        $response = new MockResponse($keys->document());

        self::assertSame($keys->publicPem, new GitHubPublicKeys(new MockHttpClient($response), new ArrayAdapter(), new MockClock())->key($keys->identifier));
        self::assertSame('https://api.github.com/meta/public_keys/secret_scanning', $response->getRequestUrl());
        self::assertSame(5.0, $response->getRequestOptions()['timeout'] ?? null);
    }

    public function testAnUnknownKeyIsLookedForAgainOncePerInterval(): void
    {
        $first = new GitHubKeys();
        $rotated = new GitHubKeys('rotated');
        $http = new MockHttpClient([new MockResponse($first->document()), new MockResponse($first->document([$rotated]))]);
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $github = new GitHubPublicKeys($http, new ArrayAdapter(), $clock, refreshInterval: 60);

        self::assertNull($github->key('rotated'));
        self::assertSame(1, $http->getRequestsCount(), 'fetched a moment ago');
        $clock->sleep(59);
        self::assertNull($github->key('rotated'));
        self::assertSame(1, $http->getRequestsCount());

        $clock->sleep(1);
        self::assertSame($rotated->publicPem, $github->key('rotated'));
        self::assertSame(2, $http->getRequestsCount());
        self::assertSame($first->publicPem, $github->key($first->identifier));
        self::assertSame(2, $http->getRequestsCount());
    }

    public function testAKeyStillUnknownAfterAFetchIsNull(): void
    {
        $keys = new GitHubKeys();
        $http = new MockHttpClient([new MockResponse($keys->document()), new MockResponse($keys->document())]);
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $github = new GitHubPublicKeys($http, new ArrayAdapter(), $clock);
        $github->key($keys->identifier);

        $clock->sleep(3600);

        self::assertNull($github->key('nope'));
        self::assertSame(2, $http->getRequestsCount());
    }

    /**
     * @return iterable<string, array{MockResponse}>
     */
    public static function failures(): iterable
    {
        yield 'not found' => [new MockResponse('{"public_keys":[]}', ['http_code' => 404])];
        yield 'a redirection' => [new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://elsewhere.example.com/']])];
        yield 'not JSON' => [new MockResponse('nope')];
        yield 'too long' => [new MockResponse([str_repeat(' ', GitHubPublicKeys::MAX_BYTES), ' ', str_repeat(' ', 1024)])];
        yield 'unreachable' => [new MockResponse('', ['error' => 'unreachable'])];
    }

    #[DataProvider('failures')]
    public function testAFailedFetchIsKeptForOneIntervalOnly(MockResponse $failure): void
    {
        $keys = new GitHubKeys();
        $http = new MockHttpClient([$failure, new MockResponse($keys->document())]);
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $cache = new ArrayAdapter();
        $github = new GitHubPublicKeys($http, $cache, $clock, lifetime: 3600, refreshInterval: 60);

        self::assertNull($github->key($keys->identifier));
        self::assertSame(['fetched_at' => $clock->now()->getTimestamp(), 'keys' => []], $cache->getItem(GitHubPublicKeys::CACHE_KEY)->get());
        $clock->sleep(60);
        self::assertSame($keys->publicPem, $github->key($keys->identifier));
    }

    public function testTheLongestAnswerIsRead(): void
    {
        $keys = new GitHubKeys();
        $document = str_pad($keys->document(), GitHubPublicKeys::MAX_BYTES, ' ');
        $github = new GitHubPublicKeys(new MockHttpClient(new MockResponse([substr($document, 0, 1000), substr($document, 1000)])), new ArrayAdapter(), new MockClock());

        self::assertSame($keys->publicPem, $github->key($keys->identifier));
    }

    public function testAFailureExpiresWithTheRefreshIntervalAndKeysWithTheLifetime(): void
    {
        $keys = new GitHubKeys();
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 500]), new MockResponse($keys->document())]);
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $cache = new ArrayAdapter(clock: $clock);
        $github = new GitHubPublicKeys($http, $cache, $clock, lifetime: 120, refreshInterval: 10);

        self::assertNull($github->key($keys->identifier));
        $clock->sleep(9);
        self::assertTrue($cache->hasItem(GitHubPublicKeys::CACHE_KEY));
        $clock->sleep(1);
        self::assertFalse($cache->hasItem(GitHubPublicKeys::CACHE_KEY), 'the failure expired from the pool');
        self::assertSame($keys->publicPem, $github->key($keys->identifier));
        $clock->sleep(119);
        self::assertTrue($cache->hasItem(GitHubPublicKeys::CACHE_KEY), 'the keys stay');
        self::assertNotNull($github->key($keys->identifier));
        self::assertSame(2, $http->getRequestsCount());
        $clock->sleep(1);
        self::assertFalse($cache->hasItem(GitHubPublicKeys::CACHE_KEY), 'the keys expired from the pool');
    }

    public function testTheDefaultsAreADayForTheKeysAndAMinuteForAFailureOrARefresh(): void
    {
        $keys = new GitHubKeys();
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $cache = new ArrayAdapter(clock: $clock);
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 500]), new MockResponse($keys->document())]);
        $github = new GitHubPublicKeys($http, $cache, $clock);

        self::assertNull($github->key($keys->identifier));
        $clock->sleep(59);
        self::assertTrue($cache->hasItem(GitHubPublicKeys::CACHE_KEY));
        self::assertNull($github->key($keys->identifier));
        self::assertSame(1, $http->getRequestsCount(), 'a failure is kept for a minute');
        $clock->sleep(1);
        self::assertFalse($cache->hasItem(GitHubPublicKeys::CACHE_KEY));

        self::assertSame($keys->publicPem, $github->key($keys->identifier));
        $clock->sleep(86_399);
        self::assertTrue($cache->hasItem(GitHubPublicKeys::CACHE_KEY), 'the keys are kept for a day');
        $clock->sleep(1);
        self::assertFalse($cache->hasItem(GitHubPublicKeys::CACHE_KEY));
    }

    public function testAnUnknownKeyIsLookedForAgainAfterAMinuteByDefault(): void
    {
        $keys = new GitHubKeys();
        $http = new MockHttpClient([new MockResponse($keys->document()), new MockResponse($keys->document())]);
        $clock = new MockClock('2026-11-12 10:00:00 UTC');
        $github = new GitHubPublicKeys($http, new ArrayAdapter(), $clock);

        self::assertNull($github->key('unknown'));
        $clock->sleep(59);
        self::assertNull($github->key('unknown'));
        self::assertSame(1, $http->getRequestsCount());
        $clock->sleep(1);
        self::assertNull($github->key('unknown'));
        self::assertSame(2, $http->getRequestsCount());
    }

    public function testAnAnswerThatIsNotOkIsNotReadEvenWhenItHoldsKeys(): void
    {
        $keys = new GitHubKeys();
        $github = new GitHubPublicKeys($this->streaming(503, static function () use ($keys): Generator {
            yield $keys->document();
        }), new ArrayAdapter(), new MockClock());

        self::assertNull($github->key($keys->identifier));
    }

    public function testAnAnswerTooLongStopsBeingReadAtTheBound(): void
    {
        $read = 0;
        $github = new GitHubPublicKeys($this->streaming(200, static function () use (&$read): Generator {
            while (true) {
                ++$read;

                yield str_repeat(' ', GitHubPublicKeys::MAX_BYTES);
            }
        }), new ArrayAdapter(), new MockClock());

        self::assertNull($github->key('any'));
        self::assertSame(2, $read, 'the answer was read up to the bound and no further');
    }

    public function testTheDocumentIsReadWithinBounds(): void
    {
        $keys = new GitHubKeys();
        $document = json_encode(['public_keys' => [
            ['key_identifier' => $keys->identifier, 'key' => $keys->publicPem],
            ['key_identifier' => 'broken', 'key' => "-----BEGIN PUBLIC KEY-----\nnope\n-----END PUBLIC KEY-----\n"],
        ]], \JSON_THROW_ON_ERROR);

        self::assertSame([$keys->identifier => $keys->publicPem], GitHubPublicKeys::parse($document));
        self::assertFalse(openssl_error_string(), 'no error left for the next request');
        self::assertSame([], GitHubPublicKeys::parse('{"public_keys":[]}'));
        self::assertCount(1, GitHubPublicKeys::parse(json_encode(['public_keys' => array_fill(0, GitHubPublicKeys::MAX_KEYS, ['key_identifier' => 'id', 'key' => $keys->publicPem])], \JSON_THROW_ON_ERROR)));
        self::assertCount(1, GitHubPublicKeys::parse(json_encode(['public_keys' => [['key_identifier' => str_repeat('i', GitHubPublicKeys::MAX_IDENTIFIER_LENGTH), 'key' => str_pad($keys->publicPem, GitHubPublicKeys::MAX_KEY_LENGTH, "\n")]]], \JSON_THROW_ON_ERROR)));
        self::assertSame([], GitHubPublicKeys::parse(str_pad('{"public_keys":[]}', GitHubPublicKeys::MAX_BYTES, ' ')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        $key = ['key_identifier' => 'id', 'key' => 'pem'];

        yield 'too long' => [str_repeat(' ', GitHubPublicKeys::MAX_BYTES + 1), 'The public keys of GitHub are 65536 bytes at most.'];
        yield 'not JSON' => ['{', 'The public keys of GitHub are JSON.'];
        yield 'too deep' => ['{"public_keys":[{"key":["x"]}]}', 'The public keys of GitHub are JSON.'];
        yield 'a list' => ['[]', 'The public keys of GitHub are a list of 32 keys at most.'];
        yield 'no list' => ['{"public_keys":{"a":1}}', 'The public keys of GitHub are a list'];
        yield 'too many' => [json_encode(['public_keys' => array_fill(0, GitHubPublicKeys::MAX_KEYS + 1, $key)], \JSON_THROW_ON_ERROR), 'The public keys of GitHub are a list'];
        yield 'a scalar key' => ['{"public_keys":["x"]}', 'Each public key'];
        yield 'no identifier' => ['{"public_keys":[{"key":"pem"}]}', 'Each public key'];
        yield 'an empty identifier' => ['{"public_keys":[{"key_identifier":"","key":"pem"}]}', 'Each public key'];
        yield 'an identifier too long' => [json_encode(['public_keys' => [['key_identifier' => str_repeat('i', GitHubPublicKeys::MAX_IDENTIFIER_LENGTH + 1), 'key' => 'pem']]], \JSON_THROW_ON_ERROR), 'Each public key'];
        yield 'no key' => ['{"public_keys":[{"key_identifier":"id"}]}', 'Each public key'];
        yield 'a key too long' => [json_encode(['public_keys' => [['key_identifier' => 'id', 'key' => str_repeat('k', GitHubPublicKeys::MAX_KEY_LENGTH + 1)]]], \JSON_THROW_ON_ERROR), 'Each public key'];
    }

    #[DataProvider('unreadable')]
    public function testAnUnreadableDocumentIsRefused(string $json, string $message): void
    {
        $this->expectException(UnreadableReport::class);
        $this->expectExceptionMessageIsOrContains($message);

        GitHubPublicKeys::parse($json);
    }

    /**
     * A client whose answer keeps streaming after it is cancelled, as nothing obliges a client to stop at once.
     *
     * @param callable(): Generator<int, string> $contents
     */
    private function streaming(int $status, callable $contents): HttpClientInterface
    {
        $response = self::createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);
        $chunks = (static function () use ($contents, $response): Generator {
            foreach ($contents() as $content) {
                $chunk = self::createStub(ChunkInterface::class);
                $chunk->method('getContent')->willReturn($content);

                yield $response => $chunk;
            }
        })();
        $stream = new readonly class($chunks) implements ResponseStreamInterface {
            /**
             * @param Generator<ResponseInterface, ChunkInterface> $chunks
             */
            public function __construct(private Generator $chunks)
            {
            }

            public function key(): ResponseInterface
            {
                return $this->chunks->key();
            }

            public function current(): ChunkInterface
            {
                return $this->chunks->current();
            }

            public function next(): void
            {
                $this->chunks->next();
            }

            public function rewind(): void
            {
            }

            public function valid(): bool
            {
                return $this->chunks->valid();
            }
        };
        $http = self::createStub(HttpClientInterface::class);
        $http->method('request')->willReturn($response);
        $http->method('stream')->willReturn($stream);

        return $http;
    }
}
