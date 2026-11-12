<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Tests\Unit\Dns;

use Phil\EmailAddress\Dns\DnsGetRecordResolver;
use Phil\EmailAddress\MailDomain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DnsGetRecordResolver::class)]
final class DnsGetRecordResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{array<int, list<array<string, mixed>>|false>, MailDomain}>
     */
    public static function answers(): iterable
    {
        $mx = ['host' => 'example.com', 'type' => 'MX', 'pri' => 10, 'target' => 'mx.example.com'];

        yield 'MX' => [[\DNS_MX => [$mx]], MailDomain::Mx];
        yield 'two MX' => [[\DNS_MX => [$mx, ['target' => '']]], MailDomain::Mx];
        yield 'null MX' => [[\DNS_MX => [['pri' => 0, 'target' => '']]], MailDomain::NullMx];
        yield 'null MX with its dot' => [[\DNS_MX => [['pri' => 0, 'target' => '.']]], MailDomain::NullMx];
        yield 'MX without target' => [[\DNS_MX => [['pri' => 0]]], MailDomain::Mx];
        yield 'failed MX' => [[\DNS_MX => false], MailDomain::Unknown];
        yield 'A' => [[\DNS_MX => [], \DNS_A => [['ip' => '192.0.2.1']]], MailDomain::AddressOnly];
        yield 'AAAA' => [[\DNS_MX => [], \DNS_A => [], \DNS_AAAA => [['ipv6' => '2001:db8::1']]], MailDomain::AddressOnly];
        yield 'failed A' => [[\DNS_MX => [], \DNS_A => false], MailDomain::Unknown];
        yield 'failed AAAA' => [[\DNS_MX => [], \DNS_A => [], \DNS_AAAA => false], MailDomain::Unknown];
        yield 'nothing' => [[\DNS_MX => [], \DNS_A => [], \DNS_AAAA => []], MailDomain::NoRecord];
    }

    /**
     * @param array<int, list<array<string, mixed>>|false> $records
     */
    #[DataProvider('answers')]
    public function testReadsTheRecords(array $records, MailDomain $expected): void
    {
        $asked = [];
        $resolver = new DnsGetRecordResolver(static function (string $host, int $type) use ($records, &$asked): array|false {
            $asked[] = [$host, $type];

            return $records[$type] ?? [];
        });

        self::assertSame($expected, $resolver->resolve('example.com'));
        self::assertSame('example.com.', $asked[0][0]);
        self::assertSame(\DNS_MX, $asked[0][1]);
        self::assertCount(\count($records), $asked);
    }
}
