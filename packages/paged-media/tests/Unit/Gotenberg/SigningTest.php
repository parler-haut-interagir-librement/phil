<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit\Gotenberg;

use Phil\PagedMedia\Gotenberg\Exception\CertificateUnavailable;
use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\Exception\InvalidStage;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Signing\Certificate;
use Phil\PagedMedia\Gotenberg\Signing\FileCertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\InlineCertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Signing\VaultCertificateSource;
use Phil\PagedMedia\Tests\Unit\Fixture\GotenbergServer;
use Phil\PagedMedia\Tests\Unit\Fixture\SelfSignedCertificate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(Certificate::class)]
#[CoversClass(FileCertificateSource::class)]
#[CoversClass(InlineCertificateSource::class)]
#[CoversClass(VaultCertificateSource::class)]
#[CoversClass(TimestampAuthorityUrl::class)]
#[CoversClass(CertificateUnavailable::class)]
final class SigningTest extends TestCase
{
    private static ?SelfSignedCertificate $signer = null;

    public function testAPkcs12CertificateIsOpenedByItsPassword(): void
    {
        $file = InputFile::fromContents('signer.PFX', 'p12');

        $certificate = Certificate::pkcs12($file, 'secret');

        self::assertSame($file, $certificate->certificate);
        self::assertNull($certificate->key);
        self::assertSame('secret', $certificate->password());
        self::assertNull(Certificate::pkcs12(InputFile::fromContents('a.p12', 'p'))->password());
    }

    public function testAPkcs12CertificateIsAP12OrPfxFile(): void
    {
        $this->expectException(InvalidStage::class);
        $this->expectExceptionMessageIsOrContains('A PKCS#12 certificate is a .p12 or .pfx file; "signer.pem" is not.');

        Certificate::pkcs12(InputFile::fromContents('signer.pem', 'p'));
    }

    public function testAPemCertificateGoesWithItsKey(): void
    {
        $pem = InputFile::fromContents('cert.pem', 'c');
        $key = InputFile::fromContents('key.pem', 'k');

        $certificate = Certificate::pem($pem, $key);

        self::assertSame($pem, $certificate->certificate);
        self::assertSame($key, $certificate->key);
        self::assertNull($certificate->password());
    }

    public function testAPemCertificateAndItsKeyAreTwoFiles(): void
    {
        $this->expectException(InvalidStage::class);
        $this->expectExceptionMessageIsOrContains('A PEM certificate and its key are two files of different names.');

        Certificate::pem(InputFile::fromContents('a.pem', 'c'), InputFile::fromContents('a.pem', 'k'));
    }

    public function testACertificateIsReadFromFiles(): void
    {
        $signer = $this->signer();
        $directory = sys_get_temp_dir().'/phil-paged-media-signing-'.getmypid();
        @mkdir($directory);
        file_put_contents($directory.'/signer.p12', $signer->pkcs12);
        file_put_contents($directory.'/cert.pem', $signer->certificate);
        file_put_contents($directory.'/key.pem', $signer->key);

        try {
            $pkcs12 = new FileCertificateSource($directory.'/signer.p12', null, $signer->password)->certificate();
            $pem = new FileCertificateSource($directory.'/cert.pem', $directory.'/key.pem')->certificate();

            self::assertSame('signer.p12', $pkcs12->certificate->name);
            self::assertSame($signer->password, $pkcs12->password());
            self::assertTrue($pkcs12->certificate->sameAs(InputFile::fromPath($directory.'/signer.p12')));
            self::assertSame('cert.pem', $pem->certificate->name);
            self::assertSame('key.pem', $pem->key?->name);
            self::assertSame($signer->key, implode('', iterator_to_array($pem->key->chunks(), false)));
            self::assertNull($pem->password());
        } finally {
            foreach (['signer.p12', 'cert.pem', 'key.pem'] as $name) {
                unlink($directory.'/'.$name);
            }

            rmdir($directory);
        }
    }

    public function testACertificateHeldAsTextIsSentUnderFixedNames(): void
    {
        $signer = $this->signer();

        $pem = new InlineCertificateSource($signer->certificate, $signer->key, 'ignored')->certificate();
        $pkcs12 = new InlineCertificateSource($signer->pkcs12, null, $signer->password)->certificate();

        self::assertTrue($pem->certificate->sameAs(InputFile::fromContents('signer.pem', $signer->certificate)));
        self::assertTrue($pem->key instanceof InputFile && $pem->key->sameAs(InputFile::fromContents('signer.key', $signer->key)));
        self::assertNull($pem->password());
        self::assertTrue($pkcs12->certificate->sameAs(InputFile::fromContents('signer.p12', $signer->pkcs12)));
        self::assertSame($signer->password, $pkcs12->password());
    }

    public function testACertificateIsReadFromAVault(): void
    {
        $signer = $this->signer();
        $server = new GotenbergServer();
        $server->answerJson(['data' => ['data' => ['certificate' => $signer->certificate, 'key' => $signer->key], 'metadata' => ['version' => 3]]]);

        $certificate = new VaultCertificateSource($server->client, 'https://vault.example.com:8200/', 'hvs.token', '/pdf/signer', '/kv/')->certificate();

        self::assertSame('GET', $server->last()['method']);
        self::assertSame('https://vault.example.com:8200/v1/kv/data/pdf/signer', $server->last()['url']);
        self::assertSame(['hvs.token'], $server->last()['headers']['x-vault-token']);
        self::assertTrue($certificate->certificate->sameAs(InputFile::fromContents('signer.pem', $signer->certificate)));
        self::assertTrue($certificate->key instanceof InputFile && $certificate->key->sameAs(InputFile::fromContents('signer.key', $signer->key)));
    }

    public function testTheVaultMountIsSecretByDefault(): void
    {
        $server = new GotenbergServer();
        $server->answerJson(['data' => ['data' => ['certificate' => 'C', 'key' => 'K']]]);

        new VaultCertificateSource($server->client, 'http://vault:8200', 't', 'signer')->certificate();

        self::assertSame('http://vault:8200/v1/secret/data/signer', $server->last()['url']);
    }

    /**
     * @param array<array-key, mixed>|string $body
     */
    #[DataProvider('vaultFailures')]
    public function testAVaultThatCannotGiveTheCertificateSaysWhy(int $status, array|string $body, string $message): void
    {
        $server = new GotenbergServer();
        $server->answer(\is_string($body) ? $body : json_encode($body, \JSON_THROW_ON_ERROR), $status, ['Content-Type' => 'application/json']);

        $this->expectException(CertificateUnavailable::class);
        $this->expectExceptionMessageIsOrContains($message);

        new VaultCertificateSource($server->client, 'http://vault:8200', 't', 'signer')->certificate();
    }

    /**
     * @return iterable<string, array{int, array<array-key, mixed>|string, string}>
     */
    public static function vaultFailures(): iterable
    {
        yield 'forbidden' => [403, ['errors' => ['permission denied']], 'The vault answered 403 for the secret http://vault:8200/v1/secret/data/signer.'];
        yield 'not json' => [200, 'nope', 'The vault answered with something other than JSON.'];
        yield 'a list' => [200, [], 'The vault answered without the "data" of a key/value secret, version 2.'];
        yield 'version 1' => [200, ['data' => ['certificate' => 'C', 'key' => 'K']], 'The vault answered without the "data" of a key/value secret, version 2.'];
        yield 'data as text' => [200, ['data' => 'x'], 'The vault answered without the "data"'];
        yield 'a scalar' => [200, '"x"', 'The vault answered without the "data"'];
        yield 'no key' => [200, ['data' => ['data' => ['certificate' => 'C']]], 'The secret http://vault:8200/v1/secret/data/signer holds no "certificate" and "key" in PEM.'];
        yield 'no certificate' => [200, ['data' => ['data' => ['key' => 'K']]], 'holds no "certificate" and "key" in PEM.'];
        yield 'an empty key' => [200, ['data' => ['data' => ['certificate' => 'C', 'key' => '']]], 'holds no "certificate" and "key" in PEM.'];
        yield 'a key as a number' => [200, ['data' => ['data' => ['certificate' => 'C', 'key' => 5]]], 'holds no "certificate" and "key" in PEM.'];
    }

    public function testAVaultOutOfReachSaysSo(): void
    {
        $server = new GotenbergServer();
        $server->answerWith(new MockResponse('', ['error' => 'Connection refused']));

        try {
            new VaultCertificateSource($server->client, 'http://vault:8200', 't', 'signer')->certificate();
            self::fail('A vault out of reach gave a certificate.');
        } catch (CertificateUnavailable $certificateUnavailable) {
            self::assertStringStartsWith('The vault could not be reached at http://vault:8200/v1/secret/data/signer: ', $certificateUnavailable->getMessage());
            self::assertNotNull($certificateUnavailable->getPrevious());
        }
    }

    public function testATimestampAuthorityIsAnHttpUrl(): void
    {
        self::assertSame('http://timestamp.example.com/tsa', new TimestampAuthorityUrl('http://timestamp.example.com/tsa')->url());

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionMessageIsOrContains('A timestamp authority is a public http or https address; "tsa.example.com" is not. ');

        new TimestampAuthorityUrl('tsa.example.com');
    }

    private function signer(): SelfSignedCertificate
    {
        return self::$signer ??= SelfSignedCertificate::make();
    }
}
