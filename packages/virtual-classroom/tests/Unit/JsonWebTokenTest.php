<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Tests\Unit;

use DateTimeImmutable;
use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\InvalidWebhook;
use Phil\VirtualClassroom\Token\JsonWebToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonWebToken::class)]
final class JsonWebTokenTest extends TestCase
{
    private const string SECRET = 'a-secret-of-thirty-two-bytes-ok!';

    public function testATokenSignedWithAnHmacIsVerified(): void
    {
        foreach (['HS256', 'HS384', 'HS512'] as $algorithm) {
            $token = JsonWebToken::sign(['meeting_id' => 'room1', 'exp' => 1_700_000_100], $algorithm, self::SECRET, ['kid' => 'k1']);
            [$header] = explode('.', $token);

            self::assertSame(['alg' => $algorithm, 'typ' => 'JWT', 'kid' => 'k1'], json_decode($this->decode($header), true));
            self::assertSame(['meeting_id' => 'room1', 'exp' => 1_700_000_100], JsonWebToken::verify($token, self::SECRET, [$algorithm], $this->now()));
        }
    }

    public function testTheHeaderAndTheClaimsAreBase64urlWithoutPadding(): void
    {
        $token = JsonWebToken::sign(['url' => 'https://example.com/a?b=c', 'name' => 'Zoé'], 'HS256', self::SECRET);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/D', $token);
        [, $claims] = explode('.', $token);
        self::assertSame('{"url":"https://example.com/a?b=c","name":"Zoé"}', $this->decode($claims));
        self::assertSame('_--8', JsonWebToken::base64url("\xFF\xEF\xBC"));
    }

    public function testAnRsaSignature(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        $token = JsonWebToken::sign(['room' => 'room1'], 'RS256', $key);
        [$header, $claims, $signature] = explode('.', $token);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($details['key']);

        self::assertSame(1, openssl_verify($header.'.'.$claims, sodium_base642bin($signature, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), $details['key'], \OPENSSL_ALGO_SHA256));
        self::assertSame('{"alg":"RS256","typ":"JWT"}', $this->decode($header));
    }

    public function testAKeyThatDoesNotFitTheAlgorithmIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The token cannot be signed with "RS256" and the key given.');

        JsonWebToken::sign([], 'RS256', self::SECRET);
    }

    public function testAnUnknownAlgorithmIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The token cannot be signed with "none" and the key given.');

        JsonWebToken::sign([], 'none', self::SECRET);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        $valid = JsonWebToken::sign(['a' => 1], 'HS256', self::SECRET);
        [$header, $claims, $signature] = explode('.', $valid);
        $part = static fn (string $json): string => JsonWebToken::base64url($json);
        $signed = static fn (string $headerJson, string $claimsJson): string => $part($headerJson).'.'.$part($claimsJson).'.'.JsonWebToken::base64url(hash_hmac('sha256', $part($headerJson).'.'.$part($claimsJson), self::SECRET, true));

        yield 'two parts' => [$header.'.'.$claims, 'The token is not a compact JSON Web Token.'];
        yield 'four parts' => [$valid.'.x', 'The token is not a compact JSON Web Token.'];
        yield 'too long' => [$header.'.'.str_repeat('a', 16_384).'.'.$signature, 'The token is not a compact JSON Web Token.'];
        yield 'another secret' => [JsonWebToken::sign(['a' => 1], 'HS256', 'another-secret-of-thirty-two-by!'), 'The signature of the token does not match.'];
        yield 'claims changed' => [$header.'.'.$part('{"a":2}').'.'.$signature, 'The signature of the token does not match.'];
        yield 'an algorithm not accepted' => [JsonWebToken::sign(['a' => 1], 'HS512', self::SECRET), 'The token is signed with an algorithm that is not accepted.'];
        yield 'none' => [$part('{"alg":"none"}').'.'.$claims.'.', 'The token is signed with an algorithm that is not accepted.'];
        yield 'no algorithm' => [$part('{"typ":"JWT"}').'.'.$claims.'.'.$signature, 'The token is signed with an algorithm that is not accepted.'];
        yield 'a header that is not base64url' => ['a+b.'.$claims.'.'.$signature, 'A part of the token is not the JSON of an object.'];
        yield 'a header that is not JSON' => [$part('not json').'.'.$claims.'.'.$signature, 'A part of the token is not the JSON of an object.'];
        yield 'a header that is a list' => [$part('["HS256"]').'.'.$claims.'.'.$signature, 'A part of the token is not the JSON of an object.'];
        yield 'a header that is a string' => [$part('"HS256"').'.'.$claims.'.'.$signature, 'A part of the token is not the JSON of an object.'];
        yield 'an empty header' => ['.'.$claims.'.'.$signature, 'A part of the token is not the JSON of an object.'];
        yield 'claims that are a list' => [$signed('{"alg":"HS256"}', '[1]'), 'A part of the token is not the JSON of an object.'];
        yield 'claims nested sixteen deep' => [$signed('{"alg":"HS256"}', str_repeat('{"a":', 16).'1'.str_repeat('}', 16)), 'A part of the token is not the JSON of an object.'];
        yield 'claims nested too deep' => [$signed('{"alg":"HS256"}', str_repeat('{"a":', 17).'1'.str_repeat('}', 17)), 'A part of the token is not the JSON of an object.'];
        yield 'expired over a minute ago' => [$signed('{"alg":"HS256"}', '{"exp":1699999939}'), 'The token has expired.'];
        yield 'an expiry that is not a number' => [$signed('{"alg":"HS256"}', '{"exp":"1800000000"}'), 'The token has expired.'];
        yield 'valid in over a minute' => [$signed('{"alg":"HS256"}', '{"nbf":1700000061}'), 'The token is not valid yet.'];
        yield 'a start that is not a number' => [$signed('{"alg":"HS256"}', '{"nbf":"1"}'), 'The token is not valid yet.'];
    }

    #[DataProvider('refused')]
    public function testARefusedToken(string $token, string $message): void
    {
        $this->expectException(InvalidWebhook::class);
        $this->expectExceptionMessageIsOrContains($message);

        JsonWebToken::verify($token, self::SECRET, ['HS256'], $this->now());
    }

    public function testTheLeewayIsAMinute(): void
    {
        $part = static fn (string $json): string => JsonWebToken::base64url($json);
        $signed = static fn (string $claimsJson): string => $part('{"alg":"HS256"}').'.'.$part($claimsJson).'.'.JsonWebToken::base64url(hash_hmac('sha256', $part('{"alg":"HS256"}').'.'.$part($claimsJson), self::SECRET, true));

        self::assertSame(['exp' => 1_699_999_940], JsonWebToken::verify($signed('{"exp":1699999940}'), self::SECRET, ['HS256'], $this->now()));
        self::assertSame(['nbf' => 1_700_000_060], JsonWebToken::verify($signed('{"nbf":1700000060}'), self::SECRET, ['HS256'], $this->now()));
        self::assertSame([], JsonWebToken::verify($signed('{}'), self::SECRET, ['HS256'], $this->now()));
        self::assertSame(['a' => str_repeat('b', 12_000)], JsonWebToken::verify($signed('{"a":"'.str_repeat('b', 12_000).'"}'), self::SECRET, ['HS256'], $this->now()));
    }

    public function testClaimsNestedFifteenDeepAreRead(): void
    {
        $part = static fn (string $json): string => JsonWebToken::base64url($json);
        $claims = str_repeat('{"a":', 15).'1'.str_repeat('}', 15);
        $token = $part('{"alg":"HS256"}').'.'.$part($claims).'.'.JsonWebToken::base64url(hash_hmac('sha256', $part('{"alg":"HS256"}').'.'.$part($claims), self::SECRET, true));

        self::assertSame(json_decode($claims, true), JsonWebToken::verify($token, self::SECRET, ['HS256'], $this->now()));
    }

    public function testATokenOfSixteenKibibytesIsRead(): void
    {
        for ($size = 11_000; $size < 13_000; ++$size) {
            $token = JsonWebToken::sign(['a' => str_repeat('b', $size)], 'HS256', self::SECRET);

            if (16_384 === \strlen($token)) {
                self::assertSame(['a' => str_repeat('b', $size)], JsonWebToken::verify($token, self::SECRET, ['HS256'], $this->now()));

                return;
            }
        }

        self::fail('No claims make a token of 16384 bytes.');
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@1700000000');
    }

    private function decode(string $part): string
    {
        return sodium_base642bin($part, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
