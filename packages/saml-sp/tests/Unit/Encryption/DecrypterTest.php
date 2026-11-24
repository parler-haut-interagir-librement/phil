<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Unit\Encryption;

use DOMDocument;
use DOMElement;
use Phil\SamlSp\Encryption\DataEncryption;
use Phil\SamlSp\Encryption\Decrypter;
use Phil\SamlSp\Exception\DecryptionFailed;
use Phil\SamlSp\Tests\Support\Keys;
use Phil\SamlSp\Tests\Support\Responses;
use Phil\SamlSp\Tests\Support\TestEncrypter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Decrypter::class)]
#[CoversClass(DataEncryption::class)]
final class DecrypterTest extends TestCase
{
    private const string XENC = 'http://www.w3.org/2001/04/xmlenc#';

    public function testTheStylesOfTheIdentityProvidersAreDecrypted(): void
    {
        foreach ([['entra', 'http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p', 'sha1'], ['okta', 'http://www.w3.org/2009/xmlenc11#rsa-oaep', 'sha256'], ['entra', 'http://www.w3.org/2009/xmlenc11#rsa-oaep', 'sha1']] as [$style, $transport, $digest]) {
            $document = Responses::entra();
            $expected = (string) $document->saveXML(Responses::assertion($document));
            TestEncrypter::encrypt(Responses::assertion($document), 'sp', $style, $transport, $digest);

            self::assertSame($expected, Decrypter::decrypt($this->encrypted($document), [Keys::privateKey('okta'), Keys::privateKey('sp')]));
        }
    }

    public function testEveryAesSizeIsRead(): void
    {
        foreach (DataEncryption::cases() as $algorithm) {
            $key = random_bytes(max(1, $algorithm->keyLength()));
            $iv = random_bytes($algorithm->isGcm() ? 12 : 16);
            $plain = '<saml:Assertion xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"/>';
            $cipher = $algorithm->isGcm()
                ? $iv.openssl_encrypt($plain, $algorithm->cipher(), $key, \OPENSSL_RAW_DATA, $iv, $tag).$tag
                : $iv.openssl_encrypt($plain, $algorithm->cipher(), $key, \OPENSSL_RAW_DATA, $iv);

            self::assertSame($plain, Decrypter::decrypt($this->build($algorithm->value, $key, $cipher), [Keys::privateKey('sp')]), $algorithm->name);
        }
    }

    public function testXmlEncryptionPaddingIsRemoved(): void
    {
        $key = random_bytes(16);
        $iv = random_bytes(16);
        $plain = str_repeat('a', 13);
        $padded = $plain."\x07\x09\x03";
        $cipher = $iv.openssl_encrypt($padded, 'aes-128-cbc', $key, \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING, $iv);

        self::assertSame($plain, Decrypter::decrypt($this->build(DataEncryption::Aes128Cbc->value, $key, $cipher), [Keys::privateKey('sp')]));
    }

    public function testThePaddingOfOneAndOfSixteenBytesIsRemoved(): void
    {
        foreach ([[str_repeat('a', 15), "\x01"], [str_repeat('a', 16), str_repeat("\x10", 16)]] as [$plain, $padding]) {
            $key = random_bytes(16);
            $iv = random_bytes(16);
            $cipher = $iv.openssl_encrypt($plain.$padding, 'aes-128-cbc', $key, \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING, $iv);

            self::assertSame($plain, Decrypter::decrypt($this->build(DataEncryption::Aes128Cbc->value, $key, $cipher), [Keys::privateKey('sp')]));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badPaddings(): iterable
    {
        yield 'seventeen after a long text' => [str_repeat('a', 16)."\x11"];
        yield 'zero' => ["\x00"];
        yield 'seventeen' => ["\x11"];
        yield 'everything' => ["\x10"];
    }

    #[DataProvider('badPaddings')]
    public function testABadPaddingReadsAsAnyFailure(string $last): void
    {
        $key = random_bytes(16);
        $iv = random_bytes(16);
        $cipher = $iv.openssl_encrypt(str_repeat('a', 15).$last, 'aes-128-cbc', $key, \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING, $iv);

        $this->expectException(DecryptionFailed::class);
        $this->expectExceptionMessageIsOrContains('The encrypted assertion cannot be decrypted with the keys of the service provider.');

        Decrypter::decrypt($this->build(DataEncryption::Aes128Cbc->value, $key, $cipher), [Keys::privateKey('sp')]);
    }

    public function testACbcCipherTextOfAnOddLengthIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The encrypted assertion cannot be decrypted with the keys of the service provider.');

        Decrypter::decrypt($this->build(DataEncryption::Aes128Cbc->value, random_bytes(16), random_bytes(16 + 20)), [Keys::privateKey('sp')]);
    }

    public function testAShortCipherTextIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The encrypted assertion cannot be decrypted with the keys of the service provider.');

        Decrypter::decrypt($this->build(DataEncryption::Aes128Gcm->value, random_bytes(16), random_bytes(43)), [Keys::privateKey('sp')]);
    }

    public function testATamperedGcmTagIsRefused(): void
    {
        $key = random_bytes(16);
        $iv = random_bytes(12);
        $cipher = $iv.openssl_encrypt(str_repeat('a', 40), 'aes-128-gcm', $key, \OPENSSL_RAW_DATA, $iv, $tag).str_repeat("\0", 16);

        $this->expectExceptionMessageIsOrContains('The encrypted assertion cannot be decrypted with the keys of the service provider.');

        Decrypter::decrypt($this->build(DataEncryption::Aes128Gcm->value, $key, $cipher), [Keys::privateKey('sp')]);
    }

    public function testAKeyOfTheWrongLengthIsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The encrypted assertion cannot be decrypted with the keys of the service provider.');

        Decrypter::decrypt($this->build(DataEncryption::Aes256Cbc->value, random_bytes(16), random_bytes(64)), [Keys::privateKey('sp')]);
    }

    public function testAnotherKeyDecryptsNothing(): void
    {
        $this->expectExceptionMessageIsOrContains('The encrypted assertion cannot be decrypted with the keys of the service provider.');

        Decrypter::decrypt($this->build(DataEncryption::Aes128Cbc->value, random_bytes(16), random_bytes(64)), [Keys::privateKey('entra')]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedTransports(): iterable
    {
        yield 'PKCS#1 v1.5' => ['<xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-1_5"/>', 'The key transport "http://www.w3.org/2001/04/xmlenc#rsa-1_5" is refused'];
        yield 'mgf1p with SHA-256' => ['<xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p"><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/></xenc:EncryptionMethod>', 'The key transport "http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p" is refused'];
        yield 'mixed digests' => ['<xenc:EncryptionMethod Algorithm="http://www.w3.org/2009/xmlenc11#rsa-oaep"><ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/></xenc:EncryptionMethod>', 'The key transport "http://www.w3.org/2009/xmlenc11#rsa-oaep" is refused'];
        yield 'unknown digest' => ['<xenc:EncryptionMethod Algorithm="http://www.w3.org/2009/xmlenc11#rsa-oaep"><ds:DigestMethod Algorithm="urn:md5"/><xenc11:MGF xmlns:xenc11="http://www.w3.org/2009/xmlenc11#" Algorithm="http://www.w3.org/2009/xmlenc11#mgf1md5"/></xenc:EncryptionMethod>', 'is refused'];
        yield 'no method' => ['', 'An EncryptedKey has one EncryptionMethod.'];
    }

    #[DataProvider('refusedTransports')]
    public function testARefusedKeyTransport(string $method, string $message): void
    {
        $this->expectException(DecryptionFailed::class);
        $this->expectExceptionMessageIsOrContains($message);

        Decrypter::decrypt($this->element('<xenc:EncryptedData><xenc:EncryptionMethod Algorithm="'.DataEncryption::Aes128Cbc->value.'"/><ds:KeyInfo><xenc:EncryptedKey>'.$method.'<xenc:CipherData><xenc:CipherValue>AAAA</xenc:CipherValue></xenc:CipherData></xenc:EncryptedKey></ds:KeyInfo><xenc:CipherData><xenc:CipherValue>AAAA</xenc:CipherValue></xenc:CipherData></xenc:EncryptedData>'), [Keys::privateKey('sp')]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformed(): iterable
    {
        $key = '<xenc:EncryptedKey><xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p"/><xenc:CipherData><xenc:CipherValue>AAAA</xenc:CipherValue></xenc:CipherData></xenc:EncryptedKey>';
        $method = '<xenc:EncryptionMethod Algorithm="'.DataEncryption::Aes128Cbc->value.'"/>';

        yield 'no data' => ['', 'An encrypted assertion holds one EncryptedData.'];
        yield 'no data method' => ['<xenc:EncryptedData><ds:KeyInfo>'.$key.'</ds:KeyInfo></xenc:EncryptedData>', 'An EncryptedData has one EncryptionMethod.'];
        yield 'unknown cipher' => ['<xenc:EncryptedData><xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#tripledes-cbc"/></xenc:EncryptedData>', 'The encryption algorithm "http://www.w3.org/2001/04/xmlenc#tripledes-cbc" is refused.'];
        yield 'no key' => ['<xenc:EncryptedData>'.$method.'</xenc:EncryptedData>', 'An encrypted assertion carries one EncryptedKey.'];
        yield 'two keys' => ['<xenc:EncryptedData>'.$method.'<ds:KeyInfo>'.$key.'</ds:KeyInfo></xenc:EncryptedData>'.$key, 'An encrypted assertion carries one EncryptedKey.'];
        yield 'no cipher value' => ['<xenc:EncryptedData>'.$method.'<ds:KeyInfo>'.$key.'</ds:KeyInfo><xenc:CipherData/></xenc:EncryptedData>', 'An encrypted value is base64 in a CipherValue.'];
        yield 'no cipher data' => ['<xenc:EncryptedData>'.$method.'<ds:KeyInfo>'.$key.'</ds:KeyInfo></xenc:EncryptedData>', 'An encrypted value is base64 in a CipherValue.'];
        yield 'bad cipher value' => ['<xenc:EncryptedData>'.$method.'<ds:KeyInfo>'.$key.'</ds:KeyInfo><xenc:CipherData><xenc:CipherValue>!</xenc:CipherValue></xenc:CipherData></xenc:EncryptedData>', 'An encrypted value is base64 in a CipherValue.'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedEncryptedAssertionIsRefused(string $content, string $message): void
    {
        $this->expectException(DecryptionFailed::class);
        $this->expectExceptionMessageIsOrContains($message);

        Decrypter::decrypt($this->element($content), [Keys::privateKey('sp')]);
    }

    public function testTheCiphers(): void
    {
        self::assertSame(['aes-128-cbc', 'aes-192-cbc', 'aes-256-cbc', 'aes-128-gcm', 'aes-192-gcm', 'aes-256-gcm'], array_map(static fn (DataEncryption $algorithm): string => $algorithm->cipher(), DataEncryption::cases()));
        self::assertSame([16, 24, 32, 16, 24, 32], array_map(static fn (DataEncryption $algorithm): int => $algorithm->keyLength(), DataEncryption::cases()));
    }

    private function encrypted(DOMDocument $document): DOMElement
    {
        $element = $document->getElementsByTagNameNS(Responses::ASSERTION, 'EncryptedAssertion')->item(0);
        self::assertInstanceOf(DOMElement::class, $element);

        return $element;
    }

    private function build(string $algorithm, string $key, string $cipher): DOMElement
    {
        openssl_public_encrypt($key, $wrapped, Keys::pem('sp.crt'), \OPENSSL_PKCS1_OAEP_PADDING);

        return $this->element('<xenc:EncryptedData><xenc:EncryptionMethod Algorithm="'.$algorithm.'"/><ds:KeyInfo><xenc:EncryptedKey><xenc:EncryptionMethod Algorithm="http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p"/><xenc:CipherData><xenc:CipherValue>'.base64_encode(\is_string($wrapped) ? $wrapped : '').'</xenc:CipherValue></xenc:CipherData></xenc:EncryptedKey></ds:KeyInfo><xenc:CipherData><xenc:CipherValue>'.base64_encode($cipher).'</xenc:CipherValue></xenc:CipherData></xenc:EncryptedData>');
    }

    private function element(string $content): DOMElement
    {
        $document = new DOMDocument();
        $document->loadXML('<saml:EncryptedAssertion xmlns:saml="'.Responses::ASSERTION.'" xmlns:xenc="'.self::XENC.'" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">'.$content.'</saml:EncryptedAssertion>');

        return Responses::root($document);
    }
}
