<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Domain;

use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfo;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(NodeInfo::class)]
#[CoversClass(NodeInfoSoftware::class)]
#[CoversClass(NodeInfoUsage::class)]
#[CoversClass(InvalidValue::class)]
final class NodeInfoTest extends TestCase
{
    public function testAFullDocument(): void
    {
        $nodeInfo = new NodeInfo(
            new NodeInfoSoftware('my-site-2', '1.0.0+build', 'https://example.com/code', 'https://example.com'),
            ['activitypub', 'zot'],
            ['atom1.0'],
            ['rss2.0', 'smtp'],
            true,
            new NodeInfoUsage(10, 5, 2, 100, 50),
            ['nodeName' => 'Example', 'staff' => ['a']],
        );

        self::assertSame(
            '{"version":"2.1","software":{"name":"my-site-2","version":"1.0.0+build","repository":"https://example.com/code","homepage":"https://example.com"},"protocols":["activitypub","zot"],"services":{"inbound":["atom1.0"],"outbound":["rss2.0","smtp"]},"openRegistrations":true,"usage":{"users":{"total":10,"activeHalfyear":5,"activeMonth":2},"localPosts":100,"localComments":50},"metadata":{"nodeName":"Example","staff":["a"]}}',
            json_encode($nodeInfo->toArray(), \JSON_UNESCAPED_SLASHES),
        );
    }

    public function testAnEmptyDocument(): void
    {
        $nodeInfo = new NodeInfo(new NodeInfoSoftware('a', '1'), ['activitypub'], [], [], false, new NodeInfoUsage(0, localComments: 0));

        self::assertSame(
            '{"version":"2.1","software":{"name":"a","version":"1"},"protocols":["activitypub"],"services":{"inbound":[],"outbound":[]},"openRegistrations":false,"usage":{"users":{"total":0},"localComments":0},"metadata":{}}',
            json_encode($nodeInfo->toArray(), \JSON_UNESCAPED_SLASHES),
        );
        self::assertSame('{"users":{}}', json_encode(new NodeInfoUsage()->toArray()));
        self::assertSame(['users' => ['activeMonth' => 0], 'localPosts' => 0], json_decode((string) json_encode(new NodeInfoUsage(activeMonth: 0, localPosts: 0)->toArray()), true));
        self::assertSame(['users' => ['activeHalfyear' => 3]], json_decode((string) json_encode(new NodeInfoUsage(activeHalfyear: 3)->toArray()), true));
        self::assertSame(str_repeat('a', 64), new NodeInfoSoftware(str_repeat('a', 64), '1')->name);
    }

    public function testCheckingValuesAgainstTheSchema(): void
    {
        NodeInfo::check('protocol', ['activitypub', 'xmpp'], NodeInfo::PROTOCOLS);

        $this->expectExceptionMessageIsOrContains('"smtp" is not a NodeInfo 2.1 protocol');
        NodeInfo::check('protocol', ['activitypub', 'smtp'], NodeInfo::PROTOCOLS);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function badDocuments(): iterable
    {
        $software = new NodeInfoSoftware('a', '1');
        $usage = new NodeInfoUsage();

        yield 'no protocol' => [static fn (): NodeInfo => new NodeInfo($software, [], [], [], false, $usage), 'A NodeInfo document names at least one protocol.'];
        yield 'an unknown protocol' => [static fn (): NodeInfo => new NodeInfo($software, ['activitypub', 'gopher'], [], [], false, $usage), '"gopher" is not a NodeInfo 2.1 protocol: activitypub, buddycloud, dfrn, diaspora, libertree, ostatus, pumpio, tent, xmpp, zot.'];
        yield 'an unknown inbound service' => [static fn (): NodeInfo => new NodeInfo($software, ['activitypub'], ['smtp'], [], false, $usage), '"smtp" is not a NodeInfo 2.1 inbound service'];
        yield 'an unknown outbound service' => [static fn (): NodeInfo => new NodeInfo($software, ['activitypub'], [], ['imap'], false, $usage), '"imap" is not a NodeInfo 2.1 outbound service'];
        yield 'a name in capitals' => [static fn (): NodeInfoSoftware => new NodeInfoSoftware('Site', '1'), 'A NodeInfo software name is 1 to 64 lowercase letters, digits and hyphens; "Site" is not one.'];
        yield 'an empty name' => [static fn (): NodeInfoSoftware => new NodeInfoSoftware('', '1'), 'software name'];
        yield 'a long name' => [static fn (): NodeInfoSoftware => new NodeInfoSoftware(str_repeat('a', 65), '1'), 'software name'];
        yield 'a name with a new line' => [static fn (): NodeInfoSoftware => new NodeInfoSoftware("a\n", '1'), 'software name'];
        yield 'no version' => [static fn (): NodeInfoSoftware => new NodeInfoSoftware('a', ''), 'A NodeInfo software has a version.'];
        yield 'an http repository' => [static fn (): NodeInfoSoftware => new NodeInfoSoftware('a', '1', 'http://e.x'), 'The repository and home page of a NodeInfo software are https:// URLs; "http://e.x" is not one.'];
        yield 'an http home page' => [static fn (): NodeInfoSoftware => new NodeInfoSoftware('a', '1', null, 'http://e.x'), 'are https:// URLs'];
        foreach (['totalUsers', 'activeHalfyear', 'activeMonth', 'localPosts', 'localComments'] as $count) {
            yield 'a negative '.$count => [static fn (): NodeInfoUsage => new NodeInfoUsage(...[$count => -1]), 'A NodeInfo count is zero or more.'];
        }
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('badDocuments')]
    public function testABadDocumentIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }
}
