<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Tests\Unit\Dkim;

use Phil\MailAuthCheck\Dkim\DkimKey;
use Phil\MailAuthCheck\Dkim\DkimKeyParser;
use Phil\MailAuthCheck\Dkim\DkimKeyType;
use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Internal\RsaKeySize;
use Phil\MailAuthCheck\Tests\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[CoversClass(DkimKeyParser::class)]
#[CoversClass(DkimKey::class)]
#[CoversClass(DkimKeyType::class)]
#[CoversClass(RsaKeySize::class)]
final class DkimKeyParserTest extends TestCase
{
    public function testAnRsaKeyIsMeasured(): void
    {
        $key = new DkimKeyParser()->parse(Keys::dkimRsa(2048));

        self::assertSame(DkimKeyType::Rsa, $key->type);
        self::assertSame(2048, $key->bits);
        self::assertFalse($key->revoked);
        self::assertFalse($key->testing);
    }

    public function testRsaIsTheDefaultTypeAndVersionIsOptional(): void
    {
        $key = new DkimKeyParser()->parse('p='.Keys::base64(Keys::rsaSpki(1024)));

        self::assertSame(DkimKeyType::Rsa, $key->type);
        self::assertSame(1024, $key->bits);
    }

    public function testTheKeyMayBeSplitBySpaces(): void
    {
        $encoded = Keys::base64(Keys::rsaSpki(2048));
        $split = implode(" \t\r\n", str_split($encoded, 60));

        self::assertSame(2048, new DkimKeyParser()->parse('k=RSA; p='.$split)->bits);
    }

    public function testABareRsaPublicKeyIsMeasuredToo(): void
    {
        self::assertSame(1023, new DkimKeyParser()->parse('p='.Keys::base64(Keys::rsaPublicKey(1023)))->bits);
    }

    #[RequiresPhpExtension('openssl')]
    public function testAKeyMadeByOpenSslIsMeasured(): void
    {
        $private = openssl_pkey_new(['private_key_bits' => 1024, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        self::assertIsArray($details);
        $pem = $details['key'];
        self::assertIsString($pem);
        $base64 = (string) preg_replace('/-----[^-]+-----|\s+/', '', $pem);

        self::assertSame(1024, new DkimKeyParser()->parse('v=DKIM1; p='.$base64)->bits);
    }

    public function testAnEd25519KeyIsRead(): void
    {
        $key = new DkimKeyParser()->parse(Keys::dkimEd25519());

        self::assertSame(DkimKeyType::Ed25519, $key->type);
        self::assertSame(256, $key->bits);
    }

    public function testAnEmptyKeyIsRevoked(): void
    {
        $key = new DkimKeyParser()->parse('v=DKIM1; k=ed25519; t=s:y; p= ');

        self::assertTrue($key->revoked);
        self::assertTrue($key->testing);
        self::assertSame(0, $key->bits);
        self::assertSame(DkimKeyType::Ed25519, $key->type);
    }

    public function testTheTestingFlagIsReadAmongOthers(): void
    {
        self::assertTrue(new DkimKeyParser()->parse(Keys::dkimRsa(2048).'; t=Y')->testing);
        self::assertTrue(new DkimKeyParser()->parse(Keys::dkimEd25519().'; t= s : y ')->testing);
        self::assertTrue(new DkimKeyParser()->parse(Keys::dkimEd25519().'; t=y')->testing);
        self::assertFalse(new DkimKeyParser()->parse(Keys::dkimEd25519().'; t=s')->testing);
        self::assertFalse(new DkimKeyParser()->parse(Keys::dkimEd25519().'; t=yy')->testing);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidKeys(): iterable
    {
        $rsa = Keys::base64(Keys::rsaSpki(2048));

        yield 'another version' => ['v=DKIM2; p='.$rsa, 'the record does not start with "v=DKIM1".'];
        yield 'a version not first' => ['p='.$rsa.'; v=DKIM1', 'the record does not start with "v=DKIM1".'];
        yield 'an unknown type' => ['k=dsa; p='.$rsa, 'the key type "dsa" is neither rsa nor ed25519.'];
        yield 'a long unknown type, cut at 20 bytes' => ['k='.str_repeat('x', 19).'yz; p='.$rsa, 'the key type "'.str_repeat('x', 19).'y" is neither rsa nor ed25519.'];
        yield 'no key' => ['v=DKIM1; k=rsa', 'the record has no public key ("p=").'];
        yield 'not base64' => ['p=@@@@', 'the public key is not base64.'];
        yield 'a short Ed25519 key' => ['k=ed25519; p='.Keys::base64(str_repeat("\x01", 31)), 'the Ed25519 public key is not 32 bytes long.'];
        yield 'a long Ed25519 key' => ['k=ed25519; p='.Keys::base64(str_repeat("\x01", 33)), 'the Ed25519 public key is not 32 bytes long.'];
        yield 'not DER' => ['p='.Keys::base64('hello'), 'the public key cannot be read: its DER structure is not a public key.'];
        yield 'a tag list error' => ['p', 'The DKIM record has a part that is not "tag=value".'];
    }

    #[DataProvider('invalidKeys')]
    public function testAnInvalidKeyIsRefused(string $text, string $message): void
    {
        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains($message);

        new DkimKeyParser()->parse($text);
    }

    public function testAKeyIsNeitherRevokedNorTestedByDefault(): void
    {
        $key = new DkimKey(DkimKeyType::Rsa, 2048);

        self::assertFalse($key->revoked);
        self::assertFalse($key->testing);
    }
}
