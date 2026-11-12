<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Tests\Unit\Application;

use Phil\BrowserSecurityBundle\Application\CookieSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieSigner::class)]
final class CookieSignerTest extends TestCase
{
    public function testASignedValueComesBack(): void
    {
        $signer = new CookieSigner('secret');
        $signed = $signer->sign('theme', 'dark.blue');

        self::assertStringStartsWith('dark.blue.', $signed);
        self::assertSame(43, \strlen(substr($signed, 10)));
        self::assertMatchesRegularExpression('/^dark\.blue\.[A-Za-z0-9_-]{43}$/D', $signed);
        self::assertSame('dark.blue', $signer->verify('theme', $signed));
    }

    public function testTheSignatureIsTheExpectedOne(): void
    {
        $key = hash_hkdf('sha256', 'secret', 32, 'phil-browser-security-cookie');
        $mac = sodium_bin2base64(hash_hmac('sha256', "theme\0dark", $key, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        self::assertSame('dark.'.$mac, new CookieSigner('secret')->sign('theme', 'dark'));

        $key = hash_hkdf('sha256', 'another', 32, 'phil-browser-security-cookie');
        $mac = sodium_bin2base64(hash_hmac('sha256', "theme\0dark", $key, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        self::assertSame('dark.'.$mac, new CookieSigner('another')->sign('theme', 'dark'));
    }

    public function testAChangedValueIsRefused(): void
    {
        $signer = new CookieSigner('secret');
        $mac = substr($signer->sign('theme', 'dark'), 5);

        self::assertNull($signer->verify('theme', 'light.'.$mac));
    }

    public function testASignatureDoesNotFollowAnotherCookie(): void
    {
        $signer = new CookieSigner('secret');

        self::assertNull($signer->verify('role', $signer->sign('theme', 'dark')));
    }

    public function testAValueWithoutSignatureIsRefused(): void
    {
        self::assertNull(new CookieSigner('secret')->verify('theme', 'dark'));
    }

    public function testAnotherSecretIsRefused(): void
    {
        self::assertNull(new CookieSigner('other')->verify('theme', new CookieSigner('secret')->sign('theme', 'dark')));
    }

    public function testAnEarlierSecretStillVerifiesAndNoLongerSigns(): void
    {
        $old = new CookieSigner('old');
        $rotated = new CookieSigner('new', ['older', 'old']);

        self::assertSame('dark', $rotated->verify('theme', $old->sign('theme', 'dark')));
        self::assertSame(new CookieSigner('new')->sign('theme', 'dark'), $rotated->sign('theme', 'dark'));
    }
}
