<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Jwt;

use Jose\Component\Signature\Algorithm\RS256;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Jwt\Base64Url;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Tests\Fixture\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Jwt::class)]
#[CoversClass(JwtSigner::class)]
#[CoversClass(Base64Url::class)]
final class JwtTest extends TestCase
{
    public function testASignedTokenIsReadBack(): void
    {
        $key = Keys::platform();
        $token = JwtSigner::sign(['iss' => 'https://lms.example.org', 'name' => 'Zoë/É'], $key);
        $jwt = Jwt::parse($token);

        self::assertSame(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'platform-1'], $jwt->header);
        self::assertSame(['iss' => 'https://lms.example.org', 'name' => 'Zoë/É'], $jwt->claims);
        self::assertSame('RS256', $jwt->algorithm());
        self::assertSame('platform-1', $jwt->keyId());
        [$header, $claims] = explode('.', $token);
        self::assertSame($header.'.'.$claims, $jwt->signingInput);
        self::assertStringContainsString('Zoë/É', Base64Url::decode($claims));
        self::assertTrue(new RS256()->verify($key->publicKey->jwk, $jwt->signingInput, $jwt->signature));
    }

    public function testEmptyClaimsAreAnObject(): void
    {
        $token = JwtSigner::sign([], Keys::platform());

        self::assertSame('e30', explode('.', $token)[1]);
        self::assertSame([], Jwt::parse($token)->claims);
    }

    public function testAMissingOrEmptyKeyIdIsNull(): void
    {
        self::assertNull(Jwt::parse($this->token(['alg' => 'RS256'], []))->keyId());
        self::assertNull(Jwt::parse($this->token(['alg' => 'RS256', 'kid' => ''], []))->keyId());
        self::assertNull(Jwt::parse($this->token(['alg' => 'RS256', 'kid' => 7], []))->keyId());
    }

    public function testBase64UrlRoundTrips(): void
    {
        self::assertSame('', Base64Url::encode(''));
        self::assertSame('', Base64Url::decode(''));
        self::assertSame('-_8', Base64Url::encode("\xfb\xff"));
        self::assertSame("\xfb\xff", Base64Url::decode('-_8'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformed(): iterable
    {
        yield 'two parts' => ['a.b', 'A token has three parts separated by dots.'];
        yield 'four parts' => ['a.b.c.d', 'A token has three parts separated by dots.'];
        yield 'not base 64' => ['a*b.e30.', 'A part of the token is not in URL-safe base 64.'];
        yield 'padded' => [Base64Url::encode('{"alg":"RS256"}').'=.e30.', 'A part of the token is not in URL-safe base 64.'];
        yield 'not JSON' => [Base64Url::encode('nope').'.e30.', 'A part of the token is not JSON.'];
        yield 'a list' => [Base64Url::encode('[1]').'.e30.', 'A part of the token is not a JSON object.'];
        yield 'a string' => [Base64Url::encode('"x"').'.e30.', 'A part of the token is not a JSON object.'];
        yield 'no algorithm' => [Base64Url::encode('{"typ":"JWT"}').'.e30.', 'The header of a token names its algorithm.'];
        yield 'numeric algorithm' => [Base64Url::encode('{"alg":1}').'.e30.', 'The header of a token names its algorithm.'];
        yield 'critical' => [Base64Url::encode('{"alg":"RS256","crit":["b64"]}').'.e30.', 'A token with critical header parameters, or an unencoded payload, is not read.'];
        yield 'unencoded payload' => [Base64Url::encode('{"alg":"RS256","b64":true}').'.e30.', 'A token with critical header parameters, or an unencoded payload, is not read.'];
        yield 'claims a list' => [Base64Url::encode('{"alg":"RS256"}').'.'.Base64Url::encode('[1,2]').'.', 'A part of the token is not a JSON object.'];
        yield 'too deep' => [Base64Url::encode('{"alg":"RS256"}').'.'.Base64Url::encode(str_repeat('{"a":', 16).'1'.str_repeat('}', 16)).'.', 'A part of the token is not JSON.'];
        yield 'too long' => [str_repeat('a', Jwt::MAX_LENGTH + 1), 'A token is 65536 bytes at most.'];
    }

    #[DataProvider('malformed')]
    public function testAMalformedTokenIsRefused(string $token, string $message): void
    {
        $this->expectException(InvalidToken::class);
        $this->expectExceptionMessageIsOrContains($message);

        Jwt::parse($token);
    }

    public function testATokenOfTheLongestLengthIsRead(): void
    {
        $prefix = Base64Url::encode('{"alg":"RS256"}').'.e30.';
        $token = $prefix.str_repeat('A', Jwt::MAX_LENGTH - \strlen($prefix));

        self::assertSame(Jwt::MAX_LENGTH, \strlen($token));
        self::assertSame('RS256', Jwt::parse($token)->algorithm());
    }

    public function testFifteenLevelsOfNestingAreRead(): void
    {
        $claims = str_repeat('{"a":', 15).'1'.str_repeat('}', 15);

        self::assertArrayHasKey('a', Jwt::parse(Base64Url::encode('{"alg":"RS256"}').'.'.Base64Url::encode($claims).'.')->claims);
    }

    public function testLargeIntegersStayText(): void
    {
        $jwt = Jwt::parse(Base64Url::encode('{"alg":"RS256"}').'.'.Base64Url::encode('{"n":123456789012345678901234567890}').'.');

        self::assertSame('123456789012345678901234567890', $jwt->claims['n']);
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $claims
     */
    private function token(array $header, array $claims): string
    {
        return Base64Url::encode(json_encode($header, \JSON_THROW_ON_ERROR)).'.'.Base64Url::encode(json_encode((object) $claims, \JSON_THROW_ON_ERROR)).'.c2ln';
    }
}
