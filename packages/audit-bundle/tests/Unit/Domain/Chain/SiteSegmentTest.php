<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Unit\Domain\Chain;

use Phil\AuditBundle\Domain\Chain\SiteSegment;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteSegment::class)]
final class SiteSegmentTest extends TestCase
{
    public function testASiteSegmentNamesItsTenantByADigest(): void
    {
        $segment = SiteSegment::of('orders', 'Shop A');

        self::assertSame('orders.t-'.substr(hash('sha256', 'Shop A'), 0, 32), $segment);
        self::assertSame(64, \strlen(SiteSegment::of(str_repeat('a', SiteSegment::MAX_BASE_LENGTH), 'x')));
        self::assertSame(29, SiteSegment::MAX_BASE_LENGTH);
        self::assertSame(32, SiteSegment::DIGEST_LENGTH);
        self::assertTrue(SiteSegment::isSite($segment));
        self::assertTrue(SiteSegment::belongsTo($segment, 'Shop A'));
        self::assertFalse(SiteSegment::belongsTo($segment, 'Shop B'));
        self::assertFalse(SiteSegment::belongsTo('orders', 'Shop A'));
        self::assertSame('orders', SiteSegment::base($segment));
        self::assertSame('orders', SiteSegment::base('orders'));
        self::assertSame('a.t-0123', SiteSegment::base('a.t-0123'), 'Too short a digest: not a site segment.');
    }

    /**
     * Test vectors: the segment of a tenant is the same on every server, and can be checked by hand with
     * `printf %s 'acme' | sha256sum | cut -c1-32`.
     */
    public function testTheDigestIsTheFirst128BitsOfTheSha256(): void
    {
        self::assertSame('orders.t-822b33ad87c148a0a20a5ba7cd5ebcaa', SiteSegment::of('orders', 'acme'));
        self::assertSame('invoices.t-a7cedb363d805843a94a18011e2c0059', SiteSegment::of('invoices', 'Coopérative du Lac'));
    }

    public function testTwoSitesWhoseDigestsShareTheirFirst64BitsAreTwoSites(): void
    {
        // Two references whose SHA-256 share their first 16 hexadecimal digits would have shared one segment with a
        // 64-bit digest; with 128 bits, a segment that agrees with a tenant's digest on 64 bits only is not its own.
        $acme = SiteSegment::of('orders', 'acme');
        $lookalike = substr($acme, 0, -16).strrev(substr($acme, -16));

        self::assertTrue(SiteSegment::isSite($lookalike));
        self::assertFalse(SiteSegment::belongsTo($lookalike, 'acme'));
        self::assertNotSame(SiteSegment::of('orders', 'acme'), SiteSegment::of('orders', 'Acme'));
        self::assertFalse(SiteSegment::belongsTo(SiteSegment::of('orders', 'acme'), 'Acme'));
        self::assertFalse(SiteSegment::belongsTo(SiteSegment::of('orders', 'Acme'), 'acme'));
    }

    public function testWhatIsNotASiteSegment(): void
    {
        foreach (['orders', '.t-0123456789abcdef0123456789abcdef', 'orders.t-0123456789ABCDEF0123456789ABCDEF', 'orders.t-0123456789abcdef0123456789abcdef0', 'orders.t-0123456789abcdef', 'orders-t-0123456789abcdef0123456789abcdef', "orders.t-0123456789abcdef0123456789abcdef\n", "orders\nx.t-0123456789abcdef0123456789abcdef"] as $segment) {
            self::assertFalse(SiteSegment::isSite($segment), $segment);
        }

        self::assertTrue(SiteSegment::isSite('o.t-0123456789abcdef0123456789abcdef'));
    }

    public function testWhatCannotBeASiteSegmentIsRefused(): void
    {
        foreach ([[str_repeat('a', SiteSegment::MAX_BASE_LENGTH + 1), 'acme'], [SiteSegment::of('orders', 'acme'), 'acme'], ['orders', ' acme'], ['orders', '']] as [$segment, $tenant]) {
            try {
                SiteSegment::of($segment, $tenant);
                self::fail(\sprintf('%s of %s was built.', $segment, $tenant));
            } catch (InvalidValue $invalidValue) {
                self::assertNotSame('', $invalidValue->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('A site segment is built on a segment of at most 29 characters that is not a site segment; "orders.t-');
        SiteSegment::of(SiteSegment::of('orders', 'acme'), 'acme');
    }
}
