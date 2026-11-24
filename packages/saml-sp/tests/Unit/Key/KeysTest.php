<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Key;

use OpenSSLAsymmetricKey;
use OpenSSLCertificateSigningRequest;
use Phil\SamlSp\Exception\InvalidKey;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSp\Key\PrivateKey;
use Phil\SamlSp\Tests\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Certificate::class)]
#[CoversClass(PrivateKey::class)]
#[CoversClass(KeyPair::class)]
final class KeysTest extends TestCase
{
    public function testACertificateIsReadFromPemAndFromBase64(): void
    {
        $certificate = Keys::certificate('entra');
        $again = Certificate::fromBase64(chunk_split($certificate->base64(), 40, "\n "));

        self::assertStringStartsWith('-----BEGIN CERTIFICATE-----', $certificate->pem);
        self::assertStringNotContainsString("\n", $certificate->base64());
        self::assertStringNotContainsString('-', $certificate->base64());
        self::assertTrue($certificate->equals($again));
        self::assertSame($certificate->pem, $again->pem);
        self::assertFalse($certificate->equals(Keys::certificate('okta')));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $certificate->fingerprint());
        self::assertSame(hash('sha256', (string) base64_decode($certificate->base64(), true)), $certificate->fingerprint());
    }

    public function testTheCertificateGivesItsPublicKey(): void
    {
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, Keys::certificate('entra')->publicKey());
    }

    public function testWhatACertificateTells(): void
    {
        $certificate = Keys::certificate('entra');

        self::assertSame(2048, $certificate->rsaBits());
        self::assertSame(1024, Keys::certificate('weak')->rsaBits());
        self::assertSame('2026-01-01T00:00:00+00:00', $certificate->notBefore()->format('c'));
        self::assertSame('2126-01-01T00:00:00+00:00', $certificate->notAfter()->format('c'));
        self::assertSame('2021-01-01T00:00:00+00:00', Keys::certificate('expired')->notAfter()->format('c'));
        self::assertSame('/CN=Test Entra ID signing', $certificate->subject());
    }

    public function testAKeyOtherThanRsaHasNoRsaBits(): void
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
        $request = $key;
        $csr = openssl_csr_new(['commonName' => 'EC'], $request);
        self::assertInstanceOf(OpenSSLCertificateSigningRequest::class, $csr);
        $x509 = openssl_csr_sign($csr, null, $key, 1);
        self::assertNotFalse($x509);
        openssl_x509_export($x509, $pem);
        self::assertIsString($pem);

        self::assertSame(0, Certificate::fromPem($pem)->rsaBits());

        openssl_pkey_export($key, $privatePem);
        self::assertIsString($privatePem);
        $this->expectException(InvalidKey::class);
        $this->expectExceptionMessageIsOrContains('The private key is not an RSA key.');
        PrivateKey::fromPem($privatePem);
    }

    public function testTextThatIsNotACertificateIsRefused(): void
    {
        $this->expectException(InvalidKey::class);
        $this->expectExceptionMessageIsOrContains('The certificate is not an X.509 certificate in PEM.');

        Certificate::fromPem('-----BEGIN CERTIFICATE-----\nnope\n-----END CERTIFICATE-----');
    }

    public function testBase64ThatIsNotACertificateIsRefused(): void
    {
        $this->expectException(InvalidKey::class);
        $this->expectExceptionMessageIsOrContains('The certificate is not base64 DER.');

        Certificate::fromBase64('***');
    }

    public function testAPrivateKeyIsReadAndHidden(): void
    {
        $key = Keys::privateKey('sp');

        self::assertSame([], $key->__debugInfo());
        self::assertSame(\OPENSSL_KEYTYPE_RSA, openssl_pkey_get_details($key->key())['type'] ?? null);
    }

    public function testAPrivateKeyWithAPassphrase(): void
    {
        openssl_pkey_export(Keys::pem('sp.key'), $encrypted, 'secret');
        self::assertIsString($encrypted);

        self::assertNotNull(PrivateKey::fromPem($encrypted, 'secret')->key());

        $this->expectException(InvalidKey::class);
        $this->expectExceptionMessageIsOrContains('The private key cannot be read: it is not PEM, or its passphrase is wrong.');
        PrivateKey::fromPem($encrypted, 'wrong');
    }

    public function testAPairIsChecked(): void
    {
        self::assertSame('/CN=Test service provider', Keys::pair('sp')->certificate->subject());

        $this->expectException(InvalidKey::class);
        $this->expectExceptionMessageIsOrContains('The private key is not the key of the certificate.');
        new KeyPair(Keys::privateKey('sp'), Keys::certificate('entra'));
    }
}
