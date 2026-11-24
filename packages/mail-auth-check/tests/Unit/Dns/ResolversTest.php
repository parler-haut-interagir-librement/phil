<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Dns;

use Override;
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Dns\DnsGetRecordResolver;
use Phil\MailAuthCheck\Dns\StaticTxtResolver;
use Phil\MailAuthCheck\Exception\DnsLookupFailed;
use Phil\MailAuthCheck\Internal\MemoizedResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DnsGetRecordResolver::class)]
#[CoversClass(StaticTxtResolver::class)]
#[CoversClass(MemoizedResolver::class)]
#[CoversClass(DnsLookupFailed::class)]
final class ResolversTest extends TestCase
{
    public function testDnsGetRecordAsksForTxtWithAFinalDotAndJoinsTheEntries(): void
    {
        $asked = [];
        $resolver = new DnsGetRecordResolver(static function (string $host, int $type) use (&$asked): array {
            $asked[] = [$host, $type];

            return [
                ['host' => 'example.org', 'type' => 'TXT', 'txt' => 'v=spf1 -all', 'entries' => ['v=spf1 ', '-all', 3]],
                ['host' => 'example.org', 'type' => 'TXT', 'txt' => 'only txt'],
                ['host' => 'example.org', 'type' => 'TXT'],
                'not a record',
            ];
        });

        self::assertSame(['v=spf1 -all', 'only txt'], $resolver->txt('example.org'));
        self::assertSame([['example.org.', \DNS_TXT]], $asked);
    }

    public function testDnsGetRecordReadsTheRecordsAfterOneThatIsNotARecord(): void
    {
        $resolver = new DnsGetRecordResolver(static fn (): array => ['not a record', ['txt' => 'after']]);

        self::assertSame(['after'], $resolver->txt('example.org'));
    }

    public function testDnsGetRecordReadsAtMost32Records(): void
    {
        $resolver = new DnsGetRecordResolver(static fn (): array => array_map(
            static fn (int $i): array => ['txt' => (string) $i],
            range(1, 40),
        ));

        $texts = $resolver->txt('example.org');

        self::assertCount(32, $texts);
        self::assertSame('32', $texts[31]);
    }

    public function testDnsGetRecordTurnsAFailureIntoAnException(): void
    {
        $resolver = new DnsGetRecordResolver(static fn (): false => false);

        $this->expectException(DnsLookupFailed::class);
        $this->expectExceptionMessageIsOrContains('The TXT records of example.org could not be read.');

        $resolver->txt('example.org');
    }

    public function testTheStaticResolverAnswersFromItsMap(): void
    {
        $resolver = new StaticTxtResolver(['example.org' => ['a', 'b']], ['down.example']);

        self::assertSame(['a', 'b'], $resolver->txt('example.org'));
        self::assertSame([], $resolver->txt('other.example'));

        $this->expectException(DnsLookupFailed::class);

        $resolver->txt('down.example');
    }

    public function testTheMemoizedResolverAsksOncePerNameFailuresIncluded(): void
    {
        $inner = new class implements TxtResolver {
            /** @var list<string> */
            public array $asked = [];

            #[Override]
            public function txt(string $asciiName): array
            {
                $this->asked[] = $asciiName;

                if ('down.example' === $asciiName) {
                    throw DnsLookupFailed::for($asciiName);
                }

                return [$asciiName, 'second'];
            }
        };
        $resolver = new MemoizedResolver($inner);

        self::assertSame(['a.example', 'second'], $resolver->txt('a.example'));
        self::assertSame(['a.example', 'second'], $resolver->txt('a.example'));

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $resolver->txt('down.example');
                self::fail('The lookup did not fail.');
            } catch (DnsLookupFailed) {
            }
        }

        self::assertSame(['a.example', 'down.example'], $inner->asked);
    }
}
