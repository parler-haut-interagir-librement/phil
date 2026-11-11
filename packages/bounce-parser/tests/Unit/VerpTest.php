<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Unit;

use Phil\BounceParser\Exception\InvalidVerp;
use Phil\BounceParser\Verp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Verp::class)]
final class VerpTest extends TestCase
{
    public function testEncodesATagAndARecipient(): void
    {
        $verp = new Verp('Bounces', 'Example.NET');

        self::assertSame('bounces+m-42@example.net', $verp->encode('m-42'));
        self::assertSame('bounces+ada=example.org@example.net', $verp->encodeRecipient('ada@example.org'));
        self::assertSame('bounces+a=b=example.org@example.net', $verp->encodeRecipient('a=b@example.org'));
        self::assertSame('bounces+ada@example.net', $verp->encodeRecipient('ada'));
        self::assertSame('bounces-x@example.net', new Verp('bounces', 'example.net', '-')->encode('x'));
    }

    public function testDecodesWhatItEncoded(): void
    {
        $verp = new Verp('bounces', 'example.net');

        self::assertSame('m-42', $verp->decode('bounces+m-42@example.net'));
        self::assertSame('m-42', $verp->decode(" bounces+m-42@example.net\t"));
        self::assertSame('m-42', $verp->decode('"News" <BOUNCES+m-42@EXAMPLE.net>'));
        self::assertSame('ada@example.org', $verp->decodeRecipient(' <bounces+ada=example.org@example.net> '));
        self::assertSame('a=b@example.org', $verp->decodeRecipient('bounces+a=b=example.org@example.net'));
        self::assertNull($verp->decodeRecipient('bounces+m-42@example.net'));
        self::assertNull($verp->decodeRecipient('ada@example.org'));
    }

    public function testDecodesOnlyItsOwnAddresses(): void
    {
        $verp = new Verp('bounces', 'example.net');

        self::assertNull($verp->decode('bounces+x@example.org'));
        self::assertNull($verp->decode('bounces+x@mail.example.net'));
        self::assertNull($verp->decode('bounces+@example.net'));
        self::assertNull($verp->decode('bounces@example.net'));
        self::assertNull($verp->decode('other+x@example.net'));
        self::assertNull($verp->decode('xbounces+x@example.net'));
        self::assertNull($verp->decode('no address'));
        self::assertSame('x', $verp->decode('b@c <bounces+x@example.net>'));
    }

    public function testRefusesAnIncompleteReturnPath(): void
    {
        foreach ([['', 'example.net', '+'], ['bounces', '', '+'], ['bounces', 'example.net', '#'], ['bounces', 'example.net', '++'], ['a+b', 'example.net', '+']] as [$local, $domain, $separator]) {
            try {
                new Verp($local, $domain, $separator);
                self::fail('The return path was accepted.');
            } catch (InvalidVerp) {
                self::addToAssertionCount(1);
            }
        }

        self::assertSame('a_b@example.net', new Verp('a', 'example.net', '_')->encode('b'));
    }

    public function testRefusesATagAnAddressCannotHold(): void
    {
        $verp = new Verp('bounces', 'example.net');

        foreach (['', 'a b', 'a@b', 'a..b', 'é'] as $tag) {
            try {
                $verp->encode($tag);
                self::fail('The tag was accepted.');
            } catch (InvalidVerp $invalid) {
                self::assertSame('A tag holds only the characters of an atom, without two dots in a row.', $invalid->getMessage());
            }
        }
    }

    public function testSignsATagThatOnlyTheKeyCanWrite(): void
    {
        $verp = new Verp('bounces', 'example.net');
        $key = str_repeat('k', 32);
        $address = $verp->encodeSigned('m-42', $key);

        self::assertMatchesRegularExpression('/^bounces\+m-42\.[0-9a-f]{32}@example\.net$/', $address);
        self::assertSame($address, $verp->encodeSigned('m-42', $key));
        self::assertNotSame($address, $verp->encodeSigned('m-43', $key));
        self::assertNotSame($address, new Verp('bounces', 'example.org')->encodeSigned('m-42', $key));
        self::assertNotSame($address, new Verp('news', 'example.net')->encodeSigned('m-42', $key));

        $tag = (string) $verp->decode($address);

        self::assertSame('m-42', $verp->decodeSigned($tag, $key));
        self::assertSame('m.42', $verp->decodeSigned((string) $verp->decode($verp->encodeSigned('m.42', $key)), $key));
        self::assertNull($verp->decodeSigned('M-42.'.substr($tag, 5), $key));
        self::assertSame('m-42', $verp->decodeSigned('m-42.'.strtoupper(substr($tag, 5)), $key));
        self::assertNull($verp->decodeSigned($tag, str_repeat('j', 32)));
        self::assertNull(new Verp('bounces', 'example.org')->decodeSigned($tag, $key));
        self::assertNull($verp->decodeSigned('m-43.'.substr($tag, 5), $key));
        self::assertNull($verp->decodeSigned(substr($tag, 0, -1), $key));
        self::assertNull($verp->decodeSigned($tag.'0', $key));
        self::assertNull($verp->decodeSigned('.'.substr($tag, 5), $key));
        self::assertNull($verp->decodeSigned('m-42', $key));
        self::assertNull($verp->decodeSigned('alice=example.org', $key));
        self::assertNull($verp->decodeSigned('', $key));
        self::assertNull($verp->decodeSigned(null, $key));
        self::assertNull($verp->decodeSigned('.'.substr(hash_hmac('sha256', 'bounces+@example.net', $key), 0, 32), $key));
        self::assertSame(substr(hash_hmac('sha256', 'bounces+m-42@example.net', $key), 0, 32), substr($tag, 5));
    }

    public function testRefusesAShortSigningKey(): void
    {
        $verp = new Verp('bounces', 'example.net');

        foreach ([static fn (): string => $verp->encodeSigned('m-42', str_repeat('k', 15)), static fn (): ?string => $verp->decodeSigned('m-42', str_repeat('k', 15))] as $call) {
            try {
                $call();
                self::fail('The key was accepted.');
            } catch (InvalidVerp $invalidVerp) {
                self::assertSame('A signing key holds at least 16 bytes.', $invalidVerp->getMessage());
            }
        }

        self::assertNull($verp->decodeSigned('m-42', str_repeat('k', 16)));

        try {
            $verp->encodeSigned('', str_repeat('k', 16));
            self::fail('The empty identifier was accepted.');
        } catch (InvalidVerp $invalidVerp) {
            self::assertSame('A tag holds only the characters of an atom, without two dots in a row.', $invalidVerp->getMessage());
        }
    }
}
