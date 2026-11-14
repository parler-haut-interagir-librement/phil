<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Monolog\Level;
use Monolog\LogRecord;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use RuntimeException;

/**
 * The processor runs on every log record of every channel: each string of the message, the context and the extra
 * fields, at any depth, goes through the URL redactor (one regex per token path prefix, plus two) and the command
 * line redactor.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"privacy", "log-redaction"})
 */
final class RedactingProcessorBench
{
    private const array DENYLIST = ['password', 'token', 'secret', 'authorization', 'cookie', 'set-cookie', 'api_key', 'query_string', '*token*', '*secret*', '*password*', 'seed*'];

    private readonly RedactingProcessor $processor;

    /**
     * @var array<int, RedactingProcessor> by the number of token path prefixes
     */
    private readonly array $byPrefixCount;

    /**
     * @var array<string, LogRecord>
     */
    private array $records = [];

    public function __construct()
    {
        $this->processor = RedactingProcessor::fromConfiguration(self::DENYLIST, ['/p/', '/reset/', '/invite/'], ['p' => 'password']);

        $byPrefixCount = [];

        foreach ([1, 10, 30] as $count) {
            $prefixes = array_map(static fn (int $i): string => \sprintf('/t%d/', $i), range(1, $count));
            $byPrefixCount[$count] = RedactingProcessor::fromConfiguration(self::DENYLIST, $prefixes);
        }

        $this->byPrefixCount = $byPrefixCount;
    }

    public function setUp(): void
    {
        $at = new DateTimeImmutable('2026-11-05 12:00:00 UTC');

        $this->records['small'] = new LogRecord($at, 'app', Level::Info, 'User signed in', ['user' => 42, 'firewall' => 'main']);

        $this->records['typical'] = new LogRecord($at, 'request', Level::Info, 'Matched route "shop_product" for GET /p/abc123def/edit?lang=fr&utm_source=mail', [
            'route' => 'shop_product',
            'route_parameters' => ['_route' => 'shop_product', '_controller' => 'App\Controller\ProductController::show', 'slug' => 'blue-mug'],
            'request_uri' => 'https://shop.example.test/p/abc123def/edit?lang=fr&utm_source=mail',
            'method' => 'GET',
            'referer' => 'https://www.example.test/search?q=mug&page=2',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Safari/605.1.15',
            'authorization' => 'Bearer eyJhbGciOiJIUzI1NiJ9.e30.abc',
            'duration_ms' => 12.4,
        ], [
            'url' => '/p/abc123def/edit?lang=fr&utm_source=mail',
            'ip' => '203.0.113.7',
            'http_method' => 'GET',
            'server' => 'shop.example.test',
            'referrer' => 'https://www.example.test/search?q=mug&page=2',
        ]);

        $nested = [];

        for ($i = 0; $i < 10; ++$i) {
            $nested['item'.$i] = [
                'dsn' => \sprintf('redis://app:secret%d@valkey:6379/%d', $i, $i),
                'target' => \sprintf('https://api.example.test/v2/orders/%d?expand=lines&access_token=t%d', $i, $i),
                'meta' => ['note' => 'Plain text with no URL in it at all, just words for the redactors to scan.', 'refreshToken' => 'r'.$i, 'label' => 'line '.$i],
            ];
        }

        $exception = new RuntimeException('Failed to reach https://user:pass@api.example.test/hook?sig=abc', 0, new RuntimeException('Connection refused for redis://app:secret@valkey:6379'));

        $this->records['large'] = new LogRecord($at, 'console', Level::Error, 'Error thrown while running command "app:import --password=hunter2 --source=https://files.example.test/export.csv?key=abc --verbose". Message: "Import failed"', [
            'exception' => $exception,
            'command' => 'app:import --password=hunter2 --source=https://files.example.test/export.csv?key=abc --verbose',
            'payload' => $nested,
        ], [
            'url' => '/admin/import?batch=12',
            'memory_usage' => '24 MB',
        ]);
    }

    /**
     * @ParamProviders("records")
     *
     * @param array{record: string} $params
     */
    public function benchInvoke(array $params): void
    {
        ($this->processor)($this->records[$params['record']]);
    }

    /**
     * @ParamProviders("prefixCounts")
     *
     * @param array{prefixes: int} $params
     */
    public function benchInvokeByTokenPathPrefixCount(array $params): void
    {
        ($this->byPrefixCount[$params['prefixes']])($this->records['typical']);
    }

    /**
     * @return Generator<string, array{record: string}>
     */
    public function records(): Generator
    {
        yield 'small (2 keys)' => ['record' => 'small'];
        yield 'typical request (13 strings)' => ['record' => 'typical'];
        yield 'large (exception, 3 levels, 60 strings)' => ['record' => 'large'];
    }

    /**
     * @return Generator<string, array{prefixes: int}>
     */
    public function prefixCounts(): Generator
    {
        yield '1 prefix' => ['prefixes' => 1];
        yield '10 prefixes' => ['prefixes' => 10];
        yield '30 prefixes' => ['prefixes' => 30];
    }
}
