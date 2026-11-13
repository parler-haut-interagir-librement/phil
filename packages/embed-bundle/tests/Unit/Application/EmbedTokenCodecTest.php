<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit\Application;

use Phil\EmbedBundle\Application\EmbedTokenCodec;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbedToken;
use Phil\EmbedBundle\Domain\ValueObject\EmbedClaims;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Domain\ValueObject\SigningKey;
use Phil\EmbedBundle\Tests\Fixtures\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(EmbedTokenCodec::class)]
#[CoversClass(InvalidEmbedToken::class)]
final class EmbedTokenCodecTest extends TestCase
{
    public function testATokenCarriesItsFormatItsAlgorithmAndItsKey(): void
    {
        $token = $this->codec()->encode($this->claims());

        self::assertMatchesRegularExpression('/^pe1\.hs256\.2026-11\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]{43}$/', $token);
        $payload = explode('.', $token)[3];
        self::assertSame('{"e":"calendar","o":"https://partner.example","i":1000,"t":"dark","s":"event/42","x":5000}', base64_decode(strtr($payload, '-_', '+/'), true));
        self::assertSame(hash_hmac('sha256', substr($token, 0, (int) strrpos($token, '.')), Keys::CURRENT, true), base64_decode(strtr(explode('.', $token)[4], '-_', '+/'), true));
    }

    public function testATokenReadsBackIntoItsClaims(): void
    {
        $claims = $this->codec()->decode($this->codec()->encode($this->claims()));

        self::assertEquals($this->claims(), $claims);
        self::assertEquals(EmbedClaims::of('form', Origin::fromString('https://p.example'), null, null, 7), $this->codec()->decode($this->codec()->encode(EmbedClaims::of('form', Origin::fromString('https://p.example'), null, null, 7))));
    }

    public function testThePreviousKeyStillVerifies(): void
    {
        $old = new EmbedTokenCodec([SigningKey::of('2026-05', Keys::PREVIOUS)], new MockClock('@2000'));

        self::assertEquals($this->claims(), $this->codec()->decode($old->encode($this->claims())));
    }

    public function testARemovedKeyNoLongerVerifies(): void
    {
        $removed = new EmbedTokenCodec([SigningKey::of('2025-11', str_repeat('o', 32))], new MockClock('@2000'));

        $this->expectExceptionObject(InvalidEmbedToken::because('The key of the token is not one of the site.'));
        $this->codec()->decode($removed->encode($this->claims()));
    }

    public function testAnExpiredToken(): void
    {
        $token = $this->codec()->encode($this->claims());

        self::assertSame(5000, new EmbedTokenCodec(Keys::both(), new MockClock('@4999'))->decode($token)->expiresAt);

        $this->expectExceptionObject(InvalidEmbedToken::because('The token has expired.'));
        new EmbedTokenCodec(Keys::both(), new MockClock('@5000'))->decode($token);
    }

    public function testLocalhostIsAnOriginOnlyWhenAllowed(): void
    {
        $claims = EmbedClaims::of('form', Origin::fromString('http://localhost:8000', true), null, null, 1);
        $token = new EmbedTokenCodec(Keys::both(), new MockClock('@2'), true)->encode($claims);

        self::assertSame('http://localhost:8000', new EmbedTokenCodec(Keys::both(), new MockClock('@2'), true)->decode($token)->origin->toString());

        $this->expectException(InvalidEmbedToken::class);
        $this->codec()->decode($token);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function forged(): iterable
    {
        $codec = new EmbedTokenCodec(Keys::both(), new MockClock('@2000'));
        $token = $codec->encode(EmbedClaims::of('calendar', Origin::fromString('https://partner.example'), null, null, 1000));
        [$format, $algorithm, $key, $payload, $signature] = explode('.', $token);
        $other = rtrim(strtr(base64_encode('{"e":"calendar","o":"https://evil.example","i":1000}'), '+/', '-_'), '=');
        $sign = static fn (string $signed, string $secret = Keys::CURRENT): string => $signed.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $signed, $secret, true)), '+/', '-_'), '=');

        yield 'too long' => [str_repeat('a', 2049), 'An embed token is 2048 bytes at most.'];
        yield 'four parts' => [implode('.', [$format, $algorithm, $key, $payload]), 'An embed token has five parts.'];
        yield 'six parts' => [$token.'.x', 'An embed token has five parts.'];
        yield 'another format' => [implode('.', ['pe2', $algorithm, $key, $payload, $signature]), 'An embed token is of the format "pe1" and the algorithm "hs256".'];
        yield 'another algorithm' => [implode('.', [$format, 'none', $key, $payload, $signature]), 'An embed token is of the format "pe1" and the algorithm "hs256".'];
        yield 'an unknown key' => [implode('.', [$format, $algorithm, 'nope', $payload, $signature]), 'The key of the token is not one of the site.'];
        yield 'claims changed' => [implode('.', [$format, $algorithm, $key, $other, $signature]), 'The signature of the token does not match.'];
        yield 'signed by the other key' => [implode('.', [$format, $algorithm, '2026-05', $payload, $signature]), 'The signature of the token does not match.'];
        yield 'no signature' => [implode('.', [$format, $algorithm, $key, $payload, '']), 'The signature of the token does not match.'];
        yield 'a signature not base64url' => [implode('.', [$format, $algorithm, $key, $payload, 'a+b/']), 'A part of the token is not base64url.'];
        yield 'claims not base64url' => [$sign(implode('.', [$format, $algorithm, $key, '!!'])), 'A part of the token is not base64url.'];
        yield 'claims not JSON' => [$sign(implode('.', [$format, $algorithm, $key, 'bm90IGpzb24'])), 'The claims of the token are not a JSON object of scalar values.'];
        yield 'claims nested' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":{"a":1}}'), '=')])), 'The claims of the token are not a JSON object of scalar values.'];
        yield 'claims a list' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('"x"'), '=')])), 'The claims of the token are e, o, i, t, s and x.'];
        yield 'an unknown claim' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":"a","o":"https://p.example","i":1,"z":1}'), '=')])), 'The claims of the token are e, o, i, t, s and x.'];
        yield 'an embed not a string' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":1,"o":"https://p.example","i":1}'), '=')])), 'The claims of the token are not of their types.'];
        yield 'an origin not a string' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":"a","o":1,"i":1}'), '=')])), 'The claims of the token are not of their types.'];
        yield 'issued not a number' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":"a","o":"https://p.example","i":"1"}'), '=')])), 'The claims of the token are not of their types.'];
        yield 'a theme not a string' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":"a","o":"https://p.example","i":1,"t":1}'), '=')])), 'The claims of the token are not of their types.'];
        yield 'a subject not a string' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":"a","o":"https://p.example","i":1,"s":1}'), '=')])), 'The claims of the token are not of their types.'];
        yield 'an expiry not a number' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":"a","o":"https://p.example","i":1,"x":"9"}'), '=')])), 'The claims of the token are not of their types.'];
        yield 'claims missing' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"o":"https://p.example","i":1}'), '=')])), 'The claims of the token are not of their types.'];
        yield 'an origin with a path' => [$sign(implode('.', [$format, $algorithm, $key, rtrim(base64_encode('{"e":"a","o":"https://p.example/x","i":1}'), '=')])), '"https://p.example/x" is not an origin: an origin has no path, query nor fragment.'];
    }

    #[DataProvider('forged')]
    public function testAForgedOrBrokenToken(string $token, string $message): void
    {
        $this->expectExceptionObject(InvalidEmbedToken::because($message));

        $this->codec()->decode($token);
    }

    public function testATokenOfExactlyTheLimitIsRead(): void
    {
        $token = $this->codec()->encode(EmbedClaims::of('calendar', Origin::fromString('https://partner.example'), null, null, 1));
        $long = str_pad($token, 2048, 'A');

        $this->expectExceptionObject(InvalidEmbedToken::because('The signature of the token does not match.'));
        $this->codec()->decode($long);
    }

    public function testTheKeysAreChecked(): void
    {
        foreach ([[[], 'Embed tokens need one key at least.'], [[SigningKey::of('k', Keys::CURRENT), SigningKey::of('k', Keys::PREVIOUS)], 'Two keys are named "k".']] as [$keys, $message]) {
            try {
                new EmbedTokenCodec($keys, new MockClock());
                self::fail('Refused.');
            } catch (InvalidEmbed $error) {
                self::assertSame($message, $error->getMessage());
            }
        }
    }

    private function claims(): EmbedClaims
    {
        return EmbedClaims::of('calendar', Origin::fromString('https://partner.example'), 'dark', 'event/42', 1000, 5000);
    }

    private function codec(): EmbedTokenCodec
    {
        return new EmbedTokenCodec(Keys::both(), new MockClock('@2000'));
    }
}
