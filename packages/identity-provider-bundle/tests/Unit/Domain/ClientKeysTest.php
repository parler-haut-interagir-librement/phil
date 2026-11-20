<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a client registers to authenticate by its keys and ask tokens for resources, and how each client is told which
 * method it may use.
 */
#[CoversClass(Client::class)]
#[CoversClass(ClientAuthMethod::class)]
#[CoversClass(IssuedToken::class)]
#[CoversClass(UsedAssertion::class)]
final class ClientKeysTest extends TestCase
{
    private const array EC = ['kty' => 'EC', 'kid' => 'k1', 'alg' => 'ES256', 'use' => 'sig', 'crv' => 'P-256', 'x' => 'eA', 'y' => 'eQ'];

    private const array OKP = ['kty' => 'OKP', 'kid' => 'k2', 'alg' => 'EdDSA', 'crv' => 'Ed25519', 'x' => 'eA'];

    public function testEachClientAcceptsItsOwnMethod(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $secret = Client::register('app', 'App', 'secret', ['https://a/cb'], [], [], false, $now);
        $public = Client::register('spa', 'SPA', null, ['https://a/cb'], [], [], false, $now);
        $keys = Client::register('machine', 'Machine', null, [], [], [], false, $now, jwks: [self::EC], resources: ['https://api/', 'https://api/']);

        $accepted = static fn (Client $client): array => array_values(array_map(static fn (ClientAuthMethod $method): string => $method->value, array_filter(ClientAuthMethod::cases(), $client->accepts(...))));
        self::assertSame(['client_secret_basic', 'client_secret_post'], $accepted($secret));
        self::assertSame(['none'], $accepted($public));
        self::assertSame(['private_key_jwt'], $accepted($keys));
        self::assertSame([false, true, false], [$keys->isPublic(), $public->isPublic(), $secret->isPublic()]);
        self::assertSame(['https://api/'], $keys->resources());
        self::assertTrue($keys->allowsResource('https://api/'));
        self::assertFalse($keys->allowsResource('https://api'));
        self::assertSame([], $secret->resources());
        self::assertSame([], $secret->jwks());
        self::assertSame([self::EC], $keys->jwks());
        self::assertSame(self::EC, $keys->publicKey('k1'));
        self::assertNull($keys->publicKey('k2'));
        self::assertFalse($keys->verifySecret(''));
        self::assertSame(ClientAuthMethod::CASES, ClientAuthMethod::cases());
        self::assertSame(['client_secret_basic', 'client_secret_post', 'private_key_jwt', 'none'], ClientAuthMethod::values());
        self::assertSame([true, true, false, false], array_map(static fn (ClientAuthMethod $method): bool => $method->usesSecret(), ClientAuthMethod::cases()));

        $keys->replaceJwks([self::OKP, self::EC]);
        self::assertSame([self::OKP, self::EC], $keys->jwks());

        $this->assertInvalid(static fn () => $secret->replaceJwks([self::EC]));
        $this->assertInvalid(static fn () => $keys->rotateSecret('new'));
        $this->assertInvalid(static fn () => $public->rotateSecret('new'));
    }

    public function testTheShapesARegistrationRefuses(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');

        $this->assertInvalid(static fn (): Client => Client::register('both', 'Both', 'secret', ['https://a/cb'], [], [], false, $now, jwks: [self::EC]));
        $this->assertInvalid(static fn (): Client => Client::register('public', 'Public', null, [], [], [], false, $now, resources: ['https://api/']));
        $this->assertInvalid(static fn (): Client => Client::register('open', 'Open', null, ['https://a/cb'], [], [], false, $now, false));
        $this->assertInvalid(static fn (): Client => Client::register('sector', 'Sector', 'secret', ['https://a/cb'], [], [], false, $now, sectorIdentifier: "a.example\n"));
        $this->assertInvalid(static fn (): Client => Client::register('sector', 'Sector', 'secret', ['https://a/cb'], [], [], false, $now, sectorIdentifier: 'A.example'));
        $this->assertInvalid(static fn (): Client => Client::register('nothing', 'Nothing', 'secret', [], [], [], false, $now));
        $this->assertInvalid(static fn (): Client => Client::register('many', 'Many', 'secret', [], [], [], false, $now, resources: array_map(static fn (int $i): string => 'https://api/'.$i, range(0, Client::MAX_RESOURCES))));

        $edge = Client::register('edge', 'Edge', 'secret', [], [], [], false, $now, resources: array_map(static fn (int $i): string => 'https://api/'.$i, range(1, Client::MAX_RESOURCES)));
        self::assertCount(Client::MAX_RESOURCES, $edge->resources());
        $listed = Client::register('listed', 'Listed', 'secret', [], [], [], false, $now, resources: ['https://a/', 'https://a/', 'https://b/']);
        self::assertSame(['https://a/', 'https://b/'], $listed->resources());
        self::assertSame('a.example', Client::register('sector', 'Sector', 'secret', ['https://a/cb'], [], [], false, $now, sectorIdentifier: 'a.example')->sector());
        $keyed = Client::register('keyed', 'Keyed', null, ['https://a/cb'], [], [], false, $now, false, [self::EC]);
        self::assertFalse($keyed->requiresPkce(), 'A client with keys is confidential: it may go without PKCE.');
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>}>
     */
    public static function refusedKeySets(): iterable
    {
        $rsa = ['kty' => 'RSA', 'kid' => 'r', 'alg' => 'RS256', 'e' => 'AQAB'];

        yield 'none' => [[]];
        yield 'too many' => [array_map(static fn (int $i): array => ['kid' => 'k'.$i] + self::EC, range(0, Client::MAX_KEYS))];
        yield 'twice the same identifier' => [[self::EC, ['x' => 'eB'] + self::EC]];
        yield 'a private member' => [[self::EC + ['d' => 'secret']]];
        yield 'a member that is not text' => [[['x' => 5] + self::EC]];
        yield 'an unknown member' => [[self::EC + ['x5c' => 'cert']]];
        yield 'no algorithm' => [[array_diff_key(self::EC, ['alg' => 1])]];
        yield 'an HMAC algorithm' => [[['alg' => 'HS256', 'kty' => 'oct'] + self::EC]];
        yield 'no identifier' => [[array_diff_key(self::EC, ['kid' => 1])]];
        yield 'an empty identifier' => [[['kid' => ''] + self::EC]];
        yield 'an identifier with a trailing newline' => [[['kid' => "k1\n"] + self::EC]];
        yield 'an identifier with a space' => [[['kid' => 'k 1'] + self::EC]];
        yield 'an identifier too long' => [[['kid' => str_repeat('k', 129)] + self::EC]];
        yield 'the type of another algorithm' => [[['kty' => 'RSA'] + self::EC]];
        yield 'for encryption' => [[['use' => 'enc'] + self::EC]];
        yield 'another curve' => [[['crv' => 'P-384'] + self::EC]];
        yield 'a missing coordinate' => [[array_diff_key(self::EC, ['y' => 1])]];
        yield 'a member of another type' => [[self::OKP + ['y' => 'eQ']]];
        yield 'a curve on RSA' => [[$rsa + ['crv' => 'P-256', 'n' => Base64Url::encode("\x01".str_repeat("\x00", 255))]]];
        yield 'an RSA modulus of 2040 bits' => [[$rsa + ['n' => Base64Url::encode("\x00\x01".str_repeat("\x00", 254))]]];
        yield 'an RSA modulus not in base64url' => [[$rsa + ['n' => '***']]];
        yield 'an RSA key without exponent' => [[array_diff_key($rsa, ['e' => 1]) + ['n' => Base64Url::encode(str_repeat("\x01", 256))]]];
    }

    /**
     * @param list<array<string, mixed>> $keys
     */
    #[DataProvider('refusedKeySets')]
    public function testAKeySetIsRefused(array $keys): void
    {
        $this->expectException(InvalidValue::class);

        Client::keySet($keys);
    }

    public function testAKeyMemberThatIsNotTextIsRefusedWithTheMembersAllowed(): void
    {
        try {
            Client::keySet([['use' => 5] + self::EC]);
            self::fail('Accepted.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('A public key of a client holds text members of a public key only: kty, kid, alg, use, n, e, crv, x, y.', $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);
        Client::keySet([self::EC + ['x5c' => 'cert']]);
    }

    public function testTheKeySetsAccepted(): void
    {
        $rsa = ['kty' => 'RSA', 'kid' => 'r', 'alg' => 'RS256', 'e' => 'AQAB', 'n' => Base64Url::encode("\x01".str_repeat("\x00", 255))];
        $five = array_map(static fn (int $i): array => ['kid' => 'k'.$i] + self::EC, range(1, Client::MAX_KEYS));

        self::assertSame([$rsa], Client::keySet([$rsa]));
        self::assertSame($five, Client::keySet($five));
        self::assertSame([['kid' => str_repeat('k', 128)] + self::EC], Client::keySet([['kid' => str_repeat('k', 128)] + self::EC]));
        self::assertSame([array_diff_key(self::EC, ['use' => 1])], Client::keySet([array_diff_key(self::EC, ['use' => 1])]));
    }

    public function testATokenOfTheClientItself(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');
        $token = IssuedToken::issue('jti', TokenKind::Access, 'FAMILY0000000000000000000A', 'machine', null, ['events:read'], null, $now, [], $now, $now, 'https://api/');

        self::assertSame([null, 'https://api/'], [$token->accountId(), $token->audience()]);
        self::assertNull(IssuedToken::issue('jti', TokenKind::Access, 'FAMILY0000000000000000000A', 'app', 'A', [], null, $now, [], $now, $now)->audience());

        $assertion = UsedAssertion::record('machine', 'jti', $now);
        self::assertSame([hash('sha256', "machine\0jti"), $now], [$assertion->id(), $assertion->expiresAt()]);
        self::assertNotSame(UsedAssertion::idOf('machine', 'jti'), UsedAssertion::idOf('machin', 'ejti'));
    }

    private function assertInvalid(callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted.');
        } catch (InvalidValue $invalidValue) {
            self::assertNotSame('', $invalidValue->getMessage());
        }
    }
}
