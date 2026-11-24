<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Tests\Unit;

use Phil\LinkChecker\LinkStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LinkStatus::class)]
final class LinkStatusTest extends TestCase
{
    public function testWhichStatusesAreBrokenOrRedirects(): void
    {
        $broken = array_map(static fn (LinkStatus $status): string => $status->value, array_values(array_filter(LinkStatus::cases(), static fn (LinkStatus $status): bool => $status->isBroken())));
        $redirects = array_map(static fn (LinkStatus $status): string => $status->value, array_values(array_filter(LinkStatus::cases(), static fn (LinkStatus $status): bool => $status->isRedirect())));

        self::assertSame(['dead', 'missing-anchor', 'invalid'], $broken);
        self::assertSame(['permanent-redirect', 'temporary-redirect'], $redirects);
        self::assertSame(['alive', 'permanent-redirect', 'temporary-redirect', 'dead', 'missing-anchor', 'invalid', 'review', 'refused'], array_map(static fn (LinkStatus $status): string => $status->value, LinkStatus::cases()));
    }
}
