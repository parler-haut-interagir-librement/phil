<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Infrastructure;

use Phil\QrCodeBundle\Infrastructure\Http\QrCodeUriSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\UriSigner;

#[CoversClass(QrCodeUriSigner::class)]
final class QrCodeUriSignerTest extends TestCase
{
    public function testTheAddressesAreSignedUnderAKeyOfTheirOwn(): void
    {
        $secret = str_repeat('s', 32);
        $url = 'https://example.com/qr-code.svg?data=HELLO';
        $signed = QrCodeUriSigner::create($secret)->sign($url);

        self::assertStringContainsString('_qr_signature=', $signed);
        self::assertSame(new UriSigner(hash_hkdf('sha256', $secret, 32, 'phil_qr_code.image_url'), '_qr_signature')->sign($url), $signed);
        self::assertTrue(QrCodeUriSigner::create($secret)->check($signed));
        self::assertFalse(new UriSigner($secret, '_qr_signature')->check($signed), 'the secret itself does not sign the images');
        self::assertFalse(QrCodeUriSigner::create($secret)->check(new UriSigner($secret, '_qr_signature')->sign($url)));
    }
}
