<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Jwt;

use DateTimeImmutable;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Jwt\Base64Url;
use Phil\Lti\Jwt\Jwt;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Jwt\JwtVerifier;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Tests\Fixture\Keys;
use Phil\Lti\Tests\Fixture\StaticKeySets;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(JwtVerifier::class)]
final class JwtVerifierTest extends TestCase
{
    private const string JWKS = 'https://lms.example.org/jwks';

    private MockClock $clock;

    private StaticKeySets $keySets;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-14 12:00:00 UTC');
        $this->keySets = new StaticKeySets()->with(self::JWKS, new KeySet([Keys::platform()->publicKey]));
    }

    public function testAValidTokenVerifies(): void
    {
        $jwt = $this->verifier()->verify($this->token(), Url::fromString(self::JWKS));

        self::assertSame('x', $jwt->claims['sub']);
        self::assertSame([[self::JWKS, false]], $this->keySets->reads);
    }

    public function testAnUnknownKeyReadsTheSetAgainOnce(): void
    {
        $this->keySets->with(self::JWKS, new KeySet([Keys::platform()->publicKey]), new KeySet([Keys::platformNext()->publicKey]));
        $verifier = $this->verifier();

        self::assertSame('x', $verifier->verify($this->token(key: 'next'), Url::fromString(self::JWKS))->claims['sub']);
        self::assertSame([[self::JWKS, false], [self::JWKS, true]], $this->keySets->reads);

        $this->keySets->with(self::JWKS, new KeySet([Keys::platform()->publicKey]));
        $this->expectExceptionMessageIsOrContains('The key set does not hold the key "platform-2".');
        $verifier->verify($this->token(key: 'next'), Url::fromString(self::JWKS));
    }

    public function testATokenWithoutKeyIdNeedsASetOfOneKey(): void
    {
        $verifier = $this->verifier();
        $jwt = Jwt::parse($this->unsigned(['alg' => 'RS256'], ['sub' => 'x']));

        try {
            $verifier->verifySignature($jwt, new KeySet([Keys::platform()->publicKey, Keys::platformNext()->publicKey]));
            self::fail('A token without key was verified among several keys.');
        } catch (InvalidToken $invalidToken) {
            self::assertSame('The token names no key, and the key set does not hold exactly one.', $invalidToken->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The signature of the token does not verify.');
        $verifier->verifySignature($jwt, new KeySet([Keys::platform()->publicKey]));
    }

    public function testAnotherAlgorithmIsRefusedWhateverTheTokenSays(): void
    {
        foreach (['none', 'HS256', 'RS512', 'rs256'] as $algorithm) {
            try {
                $this->verifier()->verify($this->unsigned(['alg' => $algorithm, 'kid' => 'platform-1'], $this->claims()), Url::fromString(self::JWKS));
                self::fail($algorithm.' was accepted.');
            } catch (InvalidToken $invalidToken) {
                self::assertSame('A token of LTI is signed with RS256.', $invalidToken->getMessage());
            }
        }

        self::assertSame([[self::JWKS, false], [self::JWKS, false], [self::JWKS, false], [self::JWKS, false]], $this->keySets->reads);
    }

    public function testATamperedTokenIsRefused(): void
    {
        [$header, , $signature] = explode('.', $this->token());
        $forged = $header.'.'.Base64Url::encode(json_encode($this->claims(['sub' => 'admin']), \JSON_THROW_ON_ERROR)).'.'.$signature;

        $this->expectExceptionMessageIsOrContains('The signature of the token does not verify.');
        $this->verifier()->verify($forged, Url::fromString(self::JWKS));
    }

    public function testTheTimesAreCheckedWithTheLeeway(): void
    {
        $now = $this->clock->now()->getTimestamp();
        $cases = [
            'expired' => [['exp' => $now - 5], 'The token has expired.'],
            'issued in the future' => [['iat' => $now + 6], 'The token is issued in the future.'],
            'too old' => [['iat' => $now - 601, 'exp' => $now + 60], 'The token is too old.'],
            'not yet' => [['nbf' => $now + 6], 'The token is not valid yet.'],
            'nbf text' => [['nbf' => 'soon'], 'The token is not valid yet.'],
            'no exp' => [['exp' => null], 'A token carries its "exp" and its "iat" as integers.'],
            'text iat' => [['iat' => (string) $now], 'A token carries its "exp" and its "iat" as integers.'],
        ];

        foreach ($cases as $name => [$claims, $message]) {
            try {
                $this->verifier()->checkTimes(array_filter($this->claims($claims), static fn (mixed $value): bool => null !== $value));
                self::fail($name.' was accepted.');
            } catch (InvalidToken $invalidToken) {
                self::assertSame($message, $invalidToken->getMessage(), $name);
            }
        }

        $this->verifier()->checkTimes($this->claims(['exp' => $now - 4, 'iat' => $now + 5, 'nbf' => $now + 5]));
        $this->verifier()->checkTimes($this->claims(['iat' => $now - 600]));
        self::addToAssertionCount(2);
    }

    #[DoesNotPerformAssertions]
    public function testTheLeewayAndTheAgeAreConfigurable(): void
    {
        $now = $this->clock->now()->getTimestamp();
        $verifier = new JwtVerifier($this->keySets, $this->clock, 0, 60);

        $verifier->checkTimes($this->claims(['exp' => $now + 1, 'iat' => $now - 60]));

        foreach ([['exp' => $now], ['iat' => $now + 1], ['iat' => $now - 61]] as $claims) {
            try {
                $verifier->checkTimes($this->claims($claims));
                self::fail('The token was accepted.');
            } catch (InvalidToken) {
            }
        }
    }

    public function testTheNonceIsRememberedForTheLongestLife(): void
    {
        self::assertEquals(new DateTimeImmutable('2026-11-14 12:10:05 UTC'), $this->verifier()->expiryOfNonce());
        self::assertSame($this->clock->now()->getTimestamp(), $this->verifier()->now());
    }

    private function verifier(): JwtVerifier
    {
        return new JwtVerifier($this->keySets, $this->clock);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function claims(array $overrides = []): array
    {
        $now = $this->clock->now()->getTimestamp();

        return $overrides + ['sub' => 'x', 'iat' => $now, 'exp' => $now + 60];
    }

    private function token(string $key = 'current'): string
    {
        return JwtSigner::sign($this->claims(), 'next' === $key ? Keys::platformNext() : Keys::platform());
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $claims
     */
    private function unsigned(array $header, array $claims): string
    {
        return Base64Url::encode(json_encode($header, \JSON_THROW_ON_ERROR)).'.'.Base64Url::encode(json_encode($claims, \JSON_THROW_ON_ERROR)).'.'.Base64Url::encode('sig');
    }
}
