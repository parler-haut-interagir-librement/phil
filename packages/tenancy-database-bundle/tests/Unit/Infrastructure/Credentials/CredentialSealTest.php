<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Credentials;

use InvalidArgumentException;
use Phil\TenancyDatabaseBundle\Domain\ValueObject\DatabaseCredentials;
use Phil\TenancyDatabaseBundle\Infrastructure\Credentials\CredentialSeal;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

#[CoversClass(CredentialSeal::class)]
final class CredentialSealTest extends TestCase
{
    private const string KEY = '0123456789abcdef0123456789abcdef';

    public function testSealedCredentialsOpenForTheirDatabase(): void
    {
        $seal = new CredentialSeal(self::KEY);
        $sealed = $seal->seal('tenant_retail', new DatabaseCredentials('retail', 'pä$$/"word', 'db.example', 6432));

        self::assertStringStartsWith('ptdb.v1.xchacha20poly1305.', $sealed);
        self::assertStringNotContainsString('retail', $sealed);
        self::assertMatchesRegularExpression('/^ptdb\.v1\.xchacha20poly1305\.[A-Za-z0-9_-]+$/', $sealed);

        $credentials = $seal->open('tenant_retail', $sealed);
        self::assertSame(['retail', 'pä$$/"word', 'db.example', 6432], [$credentials->user, $credentials->password, $credentials->host, $credentials->port]);
    }

    public function testTheCredentialsAreSealedAsCompactJson(): void
    {
        $sealed = new CredentialSeal(self::KEY)->seal('tenant_retail', new DatabaseCredentials('retail', 'a/b"ü', 'db.example', 6432));

        self::assertSame('{"u":"retail","p":"a/b\\"ü","h":"db.example","o":6432}', $this->plain('tenant_retail', $sealed));
    }

    public function testCredentialsWithoutAServerOpenWithoutOne(): void
    {
        $seal = new CredentialSeal(self::KEY);
        $credentials = $seal->open('tenant_retail', $seal->seal('tenant_retail', new DatabaseCredentials('retail', 'secret')));

        self::assertNull($credentials->host);
        self::assertNull($credentials->port);
    }

    public function testEachSealIsDifferent(): void
    {
        $seal = new CredentialSeal(self::KEY);
        $credentials = new DatabaseCredentials('retail', 'secret');

        self::assertNotSame($seal->seal('tenant_retail', $credentials), $seal->seal('tenant_retail', $credentials));
    }

    public function testCredentialsSealedForAnotherDatabaseDoNotOpen(): void
    {
        $seal = new CredentialSeal(self::KEY);
        $sealed = $seal->seal('tenant_retail', new DatabaseCredentials('retail', 'secret'));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The sealed credentials do not open for the database "tenant_wholesale": another database, another key, or altered.');

        $seal->open('tenant_wholesale', $sealed);
    }

    public function testCredentialsSealedUnderAnotherKeyDoNotOpen(): void
    {
        $sealed = new CredentialSeal(self::KEY)->seal('tenant_retail', new DatabaseCredentials('retail', 'secret'));

        $this->expectException(UnexpectedValueException::class);

        new CredentialSeal(str_repeat('k', 32))->open('tenant_retail', $sealed);
    }

    public function testAlteredCredentialsDoNotOpen(): void
    {
        $seal = new CredentialSeal(self::KEY);
        $sealed = $seal->seal('tenant_retail', new DatabaseCredentials('retail', 'secret'));
        $altered = substr($sealed, 0, -2).(str_ends_with($sealed, 'AA') ? 'BB' : 'AA');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('do not open');

        $seal->open('tenant_retail', $altered);
    }

    public function testTheFormatIsNamedWithoutItsLastDot(): void
    {
        try {
            new CredentialSeal(self::KEY)->open('tenant_retail', 'ptdb.v2.xchacha20poly1305.AAAA');
            self::fail('The value was not refused.');
        } catch (UnexpectedValueException $unexpectedValueException) {
            self::assertSame('The sealed credentials are not of the format ptdb.v1.xchacha20poly1305.', $unexpectedValueException->getMessage());
        }
    }

    public function testAValueOfTheLongestLengthIsRead(): void
    {
        $sealed = CredentialSeal::PREFIX.str_repeat('A', CredentialSeal::MAX_LENGTH - \strlen(CredentialSeal::PREFIX));
        self::assertSame(CredentialSeal::MAX_LENGTH, \strlen($sealed));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('do not open');

        new CredentialSeal(self::KEY)->open('tenant_retail', $sealed);
    }

    public function testAnEmptyDocumentIsSealedAndHoldsNoCredentials(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The sealed credentials do not hold credentials.');

        new CredentialSeal(self::KEY)->open('tenant_retail', $this->sealedPlain(''));
    }

    public function testAValueBetweenANonceAndAWholeSealIsRefused(): void
    {
        // 20 bytes: shorter than a nonce, which libsodium refuses with an error of its own.
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('do not open');

        new CredentialSeal(self::KEY)->open('tenant_retail', CredentialSeal::PREFIX.sodium_bin2base64(str_repeat('a', 20), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notSealed(): iterable
    {
        yield 'another format' => ['ptdb.v2.xchacha20poly1305.AAAA', 'The sealed credentials are not of the format ptdb.v1.xchacha20poly1305.'];
        yield 'another algorithm' => ['ptdb.v1.aes256gcm.AAAA', 'The sealed credentials are not of the format ptdb.v1.xchacha20poly1305.'];
        yield 'too long' => ['ptdb.v1.xchacha20poly1305.'.str_repeat('A', CredentialSeal::MAX_LENGTH), 'The sealed credentials are not of the format ptdb.v1.xchacha20poly1305.'];
        yield 'not base64url' => ['ptdb.v1.xchacha20poly1305.a+b/', 'The sealed credentials are not valid base64url.'];
        yield 'shorter than a nonce' => ['ptdb.v1.xchacha20poly1305.AAAA', 'do not open'];
        yield 'empty' => ['ptdb.v1.xchacha20poly1305.', 'do not open'];
    }

    #[DataProvider('notSealed')]
    public function testAValueThatIsNotSealedCredentialsIsRefused(string $sealed, string $message): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new CredentialSeal(self::KEY)->open('tenant_retail', $sealed);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notCredentials(): iterable
    {
        yield 'not JSON' => ['user=retail'];
        yield 'too deep' => ['{"u":{"x":1},"p":"s"}'];
        yield 'no user' => ['{"p":"secret"}'];
        yield 'no password' => ['{"u":"retail"}'];
        yield 'a numeric password' => ['{"u":"retail","p":1}'];
        yield 'a numeric host' => ['{"u":"retail","p":"s","h":1}'];
        yield 'a textual port' => ['{"u":"retail","p":"s","o":"5432"}'];
        yield 'a list' => ['["retail","secret"]'];
        yield 'a string' => ['"retail"'];
    }

    #[DataProvider('notCredentials')]
    public function testASealedValueThatHoldsNoCredentialsIsRefused(string $plain): void
    {
        $sealed = $this->sealedPlain($plain);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('The sealed credentials do not hold credentials.');

        new CredentialSeal(self::KEY)->open('tenant_retail', $sealed);
    }

    public function testAKeyOfAnotherLengthIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The key of the credential seal is 32 bytes.');

        new CredentialSeal('short');
    }

    private function sealedPlain(string $plain): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        return CredentialSeal::PREFIX.sodium_bin2base64($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, CredentialSeal::PREFIX.'tenant_retail', $nonce, self::KEY), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    private function plain(string $database, string $sealed): string
    {
        $binary = sodium_base642bin(substr($sealed, \strlen(CredentialSeal::PREFIX)), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $nonceLength = \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($binary, $nonceLength), CredentialSeal::PREFIX.$database, substr($binary, 0, $nonceLength), self::KEY);
        self::assertIsString($plain);

        return $plain;
    }
}
