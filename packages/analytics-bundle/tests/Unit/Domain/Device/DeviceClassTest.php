<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Device;

use Phil\AnalyticsBundle\Domain\Device\DeviceClass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceClass::class)]
final class DeviceClassTest extends TestCase
{
    /**
     * @return iterable<string, array{string, DeviceClass}>
     */
    public static function userAgents(): iterable
    {
        yield 'desktop Firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0', DeviceClass::Desktop];
        yield 'desktop Safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15', DeviceClass::Desktop];
        yield 'iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', DeviceClass::Mobile];
        yield 'Android phone' => ['Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36', DeviceClass::Mobile];
        yield 'iPad' => ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', DeviceClass::Tablet];
        yield 'Android tablet' => ['Mozilla/5.0 (Linux; Android 15; SM-X910) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', DeviceClass::Tablet];
        yield 'Kindle' => ['Mozilla/5.0 (Linux; Android 11; KFTRWI) AppleWebKit/537.36 (KHTML, like Gecko) Silk/120.0 like Chrome/120.0 Safari/537.36', DeviceClass::Tablet];
        yield 'Windows Tablet PC' => ['Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; Tablet PC 2.0)', DeviceClass::Desktop];
        yield 'Windows Tablet PC on a phone' => ['Mozilla/5.0 (Windows Phone 10.0; Tablet PC 2.0)', DeviceClass::Mobile];
        yield 'empty' => ['', DeviceClass::Desktop];
        yield 'lowercase tablet' => ['some-ipad-client/1.0', DeviceClass::Tablet];
        yield 'lowercase android tablet' => ['android-client/1.0', DeviceClass::Tablet];
        yield 'lowercase android phone' => ['android mobile client/1.0', DeviceClass::Mobile];
        yield 'lowercase phone' => ['iphone-client/1.0', DeviceClass::Mobile];
    }

    #[DataProvider('userAgents')]
    public function testAUserAgentFallsInOneOfThreeClasses(string $userAgent, DeviceClass $expected): void
    {
        self::assertSame($expected, DeviceClass::fromUserAgent($userAgent));
    }
}
