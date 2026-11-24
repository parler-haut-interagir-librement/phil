<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Integration;

use Phil\MailAuthCheck\Dns\DnsGetRecordResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The system's resolver, asked for real: run only when PHIL_TEST_DNS is set, as it needs a network.
 */
#[CoversClass(DnsGetRecordResolver::class)]
final class SystemResolverTest extends TestCase
{
    public function testANameThatNeverResolvesHasNoRecord(): void
    {
        if (false === getenv('PHIL_TEST_DNS')) {
            self::markTestSkipped('Set PHIL_TEST_DNS to ask the system resolver.');
        }

        // `.invalid` never resolves (RFC 6761).
        self::assertSame([], new DnsGetRecordResolver()->txt('nothing.invalid'));
    }
}
