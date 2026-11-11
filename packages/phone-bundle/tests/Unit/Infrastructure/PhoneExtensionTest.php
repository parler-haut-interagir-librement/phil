<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Unit\Infrastructure;

use Phil\Phone\PhoneNumber;
use Phil\PhoneBundle\Infrastructure\Twig\PhoneExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

#[CoversClass(PhoneExtension::class)]
final class PhoneExtensionTest extends TestCase
{
    public function testANumberIsWrittenInAFormat(): void
    {
        $extension = new PhoneExtension('CA');
        $number = PhoneNumber::parse('+15145550123');

        self::assertSame('+1 514-555-0123', $extension->format($number));
        self::assertSame('(514) 555-0123', $extension->format('514 555 0123', 'national'));
        self::assertSame('+15145550123', $extension->format($number, 'e164'));
        self::assertSame('tel:+1-514-555-0123', $extension->format($number, 'rfc3966'));
        self::assertSame('not a number', $extension->format('not a number'));
        self::assertSame('', $extension->format(null));
        self::assertSame('514 555 0123', new PhoneExtension()->format('514 555 0123'));
    }

    public function testANumberIsLinked(): void
    {
        $extension = new PhoneExtension('FR');

        self::assertSame('tel:+33-6-12-34-56-78', $extension->link('06 12 34 56 78'));
        self::assertSame('tel:+1-514-555-0123;ext=1', $extension->link(PhoneNumber::parse('+15145550123;ext=1')));
        self::assertNull($extension->link('not a number'));
        self::assertNull($extension->link(null));
    }

    public function testAnUnknownFormatIsRefused(): void
    {
        $this->expectException(ValueError::class);

        new PhoneExtension()->format(PhoneNumber::parse('+15145550123'), 'local');
    }
}
