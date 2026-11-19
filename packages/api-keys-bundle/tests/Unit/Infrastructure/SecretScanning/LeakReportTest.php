<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Infrastructure\SecretScanning;

use JsonException;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\Exception\UnreadableReport;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\LeakReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LeakReport::class)]
#[CoversClass(UnreadableReport::class)]
final class LeakReportTest extends TestCase
{
    public function testTheTokenAndTheTypeOfEachMatchAreKept(): void
    {
        $report = LeakReport::fromJson('[{"token":"acme_one","type":"acme_api_token","url":"https://github.com/o/r/blob/x/f","source":"content"},{"token":"x","type":""}]');

        self::assertSame([['token' => 'acme_one', 'type' => 'acme_api_token'], ['token' => 'x', 'type' => '']], $report->matches);
        self::assertSame([], LeakReport::fromJson('[]')->matches);
    }

    public function testTheBoundsAreReachable(): void
    {
        $matches = array_fill(0, LeakReport::MAX_MATCHES, ['token' => str_repeat('t', LeakReport::MAX_TOKEN_LENGTH), 'type' => str_repeat('y', LeakReport::MAX_TYPE_LENGTH)]);

        self::assertCount(LeakReport::MAX_MATCHES, LeakReport::fromJson(json_encode($matches, \JSON_THROW_ON_ERROR))->matches);

        $padded = '['.str_repeat(' ', LeakReport::MAX_BYTES - 2).']';
        self::assertSame([], LeakReport::fromJson($padded)->matches);
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function unreadable(): iterable
    {
        yield 'too long' => ['['.str_repeat(' ', LeakReport::MAX_BYTES - 1).']', 'A report of leaked tokens is 1048576 bytes at most.'];
        yield 'not JSON' => ['[{', 'A report of leaked tokens is JSON.'];
        yield 'too deep' => ['[{"token":["x"]}]', 'A report of leaked tokens is JSON.'];
        yield 'an object' => ['{"token":"x","type":"y"}', 'A report of leaked tokens is a list.'];
        yield 'a string' => ['"x"', 'A report of leaked tokens is a list.'];
        yield 'too many' => [json_encode(array_fill(0, LeakReport::MAX_MATCHES + 1, ['token' => 't', 'type' => 'y']), \JSON_THROW_ON_ERROR), 'A report of leaked tokens holds 1000 matches at most.'];
        yield 'a scalar match' => ['["x"]', 'Each match'];
        yield 'no token' => ['[{"type":"y"}]', 'Each match'];
        yield 'an empty token' => ['[{"token":"","type":"y"}]', 'Each match'];
        yield 'a token not a string' => ['[{"token":12,"type":"y"}]', 'Each match'];
        yield 'a token too long' => ['[{"token":"'.str_repeat('t', LeakReport::MAX_TOKEN_LENGTH + 1).'","type":"y"}]', 'Each match'];
        yield 'no type' => ['[{"token":"x"}]', 'Each match'];
        yield 'a type not a string' => ['[{"token":"x","type":null}]', 'Each match'];
        yield 'a type too long' => ['[{"token":"x","type":"'.str_repeat('y', LeakReport::MAX_TYPE_LENGTH + 1).'"}]', 'Each match'];
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('unreadable')]
    public function testAnUnreadableBodyIsRefused(string $json, string $message): void
    {
        try {
            LeakReport::fromJson($json);
            self::fail('The body was read.');
        } catch (UnreadableReport $unreadableReport) {
            self::assertStringStartsWith($message, $unreadableReport->getMessage());
            self::assertSame(0, $unreadableReport->getCode());
        }
    }

    public function testTheJsonErrorIsKept(): void
    {
        try {
            LeakReport::fromJson('nope');
            self::fail('The body was read.');
        } catch (UnreadableReport $unreadableReport) {
            self::assertInstanceOf(JsonException::class, $unreadableReport->getPrevious());
        }
    }
}
