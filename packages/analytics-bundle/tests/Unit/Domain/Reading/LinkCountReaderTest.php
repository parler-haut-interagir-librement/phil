<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Reading;

use Phil\AnalyticsBundle\Domain\Measure\DeclaredOutboundLink;
use Phil\AnalyticsBundle\Domain\Reading\LinkCountReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkCountReader::class)]
final class LinkCountReaderTest extends TestCase
{
    public function testAnAuthorSeesTheirOwnLinksOnly(): void
    {
        $mine = DeclaredOutboundLink::of('l1', 'alice');
        $other = DeclaredOutboundLink::of('l2', 'bob');
        $reader = LinkCountReader::author('alice');

        self::assertTrue($reader->maySee($mine));
        self::assertFalse($reader->maySee($other));
        self::assertSame(['l1' => 1], $reader->counts([$mine, $other], ['l1' => 1, 'l2' => 9]));
    }

    public function testAnAdministratorSeesEveryLinkAndExactCounts(): void
    {
        $links = [DeclaredOutboundLink::of('l1', 'alice'), DeclaredOutboundLink::of('l2', 'bob')];

        self::assertSame(['l1' => 1, 'l2' => 0], LinkCountReader::administrator()->counts($links, ['l1' => 1]));
    }
}
