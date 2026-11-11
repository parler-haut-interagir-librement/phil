<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Unit\Infrastructure;

use Locale;
use Phil\Address\Address;
use Phil\AddressBundle\Infrastructure\Twig\AddressExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddressExtension::class)]
final class AddressExtensionTest extends TestCase
{
    public function testAnAddressIsWrittenInALanguage(): void
    {
        $extension = new AddressExtension();
        $tokyo = Address::of('JP', ['addressLine1' => '千代田1-1', 'locality' => '千代田区', 'administrativeArea' => 'Tokyo', 'postalCode' => '100-0001']);
        $locale = Locale::getDefault();
        Locale::setDefault('ja');

        try {
            self::assertSame("〒100-0001\n東京都千代田区\n千代田1-1", $extension->format($tokyo));
            self::assertSame(['〒100-0001', '東京都千代田区', '千代田1-1'], $extension->lines($tokyo));
            self::assertSame('カナダ', $extension->country('CA'));
            self::assertStringEndsWith('日本', $extension->label($tokyo, 'CA'));
            self::assertSame("千代田1-1\n千代田区, Tokyo\n100-0001", $extension->format($tokyo, 'en'));
            self::assertSame(['千代田1-1', '千代田区, Tokyo', '100-0001'], $extension->lines($tokyo, 'en'));
            self::assertSame("千代田1-1\n千代田区, TOKYO\n100-0001\nJAPAN", $extension->label($tokyo, 'CA', 'en'));
            self::assertSame('Canada', $extension->country('ca', 'fr'));
        } finally {
            Locale::setDefault($locale);
        }

        self::assertSame(['', [], ''], [$extension->format(null), $extension->lines(null), $extension->label(null, 'CA')]);
    }

    public function testAnAddressIsAPostalAddress(): void
    {
        self::assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Montréal', 'addressCountry' => 'CA'], new AddressExtension()->schema(Address::of('CA', ['locality' => 'Montréal'])));
        self::assertNull(new AddressExtension()->schema(null));
    }
}
