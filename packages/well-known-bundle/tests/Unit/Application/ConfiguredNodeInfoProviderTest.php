<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Application;

use Phil\WellKnownBundle\Application\ConfiguredNodeInfoProvider;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoSoftware;
use Phil\WellKnownBundle\Domain\ValueObject\NodeInfoUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfiguredNodeInfoProvider::class)]
final class ConfiguredNodeInfoProviderTest extends TestCase
{
    public function testItTellsItsConfiguration(): void
    {
        $software = new NodeInfoSoftware('site', '1.0');
        $provider = new ConfiguredNodeInfoProvider($software, true, ['nodeName' => 'Site']);

        self::assertSame($software, $provider->software());
        self::assertTrue($provider->openRegistrations());
        self::assertFalse(new ConfiguredNodeInfoProvider($software, false, [])->openRegistrations());
        self::assertSame(['nodeName' => 'Site'], $provider->metadata());
        self::assertEquals(new NodeInfoUsage(), $provider->usage());
    }
}
