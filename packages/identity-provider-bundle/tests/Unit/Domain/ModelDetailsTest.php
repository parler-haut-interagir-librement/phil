<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Phil\IdentityProviderBundle\Domain\Encoding\Base32;
use Phil\IdentityProviderBundle\Domain\Exception\AuthorizationError;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\External\LinkedIdentity;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Consent;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\SigningKey;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequest;
use Phil\IdentityProviderBundle\Domain\Oidc\AuthorizationRequestParser;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Domain\Oidc\TokenKind;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Domain\Security\RecoveryCodes;
use Phil\IdentityProviderBundle\Domain\Totp\Totp;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * The details of the models and the small domain services that one test of their behaviour does not reach: what a list
 * keeps and in what shape, what a second call leaves alone, the exact text that is hashed.
 */
#[CoversClass(Account::class)]
#[CoversClass(AccountSession::class)]
#[CoversClass(AuthorizationCode::class)]
#[CoversClass(AuthorizationRequestParser::class)]
#[CoversClass(Client::class)]
#[CoversClass(Consent::class)]
#[CoversClass(ExternalIdentity::class)]
#[CoversClass(IssuedToken::class)]
#[CoversClass(LinkedIdentity::class)]
#[CoversClass(LockoutPolicy::class)]
#[CoversClass(Passkey::class)]
#[CoversClass(RecoveryCodes::class)]
#[CoversClass(SigningKey::class)]
#[CoversClass(Totp::class)]
final class ModelDetailsTest extends TestCase
{
    private const string ADA = '01JCZ6Q7Y3W0000000000000AA';

    public function testAnAddressIsFoldedAsText(): void
    {
        self::assertSame('émile@example.com', Account::normalizeEmail(' ÉMILE@Example.COM '));
        self::assertSame(str_repeat('é', 100).'@a.bc', Account::normalizeEmail(str_repeat('É', 100).'@A.BC'));
        self::assertSame(180, mb_strlen(Account::normalizeEmail(str_repeat('é', 175).'@a.bc')));
    }

    public function testTheRolesAreAListWithoutDuplicates(): void
    {
        $account = Account::register(self::ADA, 'ada@example.com', new DateTimeImmutable());
        $account->grantRoles(['ROLE_USER', 'ROLE_X', 'ROLE_X', 'ROLE_Y']);

        self::assertSame(['ROLE_X', 'ROLE_Y'], $this->read($account, 'roles'));
        self::assertSame(['ROLE_USER', 'ROLE_X', 'ROLE_Y'], $account->roles());

        new ReflectionProperty(Account::class, 'roles')->setValue($account, ['ROLE_X', 'ROLE_X', 'ROLE_Y']);
        self::assertSame(['ROLE_USER', 'ROLE_X', 'ROLE_Y'], $account->roles());
    }

    public function testASecondCallLeavesTheFirstMomentAlone(): void
    {
        $first = new DateTimeImmutable('2026-11-14 10:00:00');
        $second = $first->modify('+1 hour');

        $code = AuthorizationCode::issue('code', 'app', self::ADA, 'https://a/cb', ['openid'], null, 'challenge', $first, [], null, 'FAMILY0000000000000000000A', $second);
        $code->use($first);
        $code->use($second);
        self::assertSame($first, $this->read($code, 'usedAt'));

        $token = IssuedToken::issue('jti', TokenKind::Refresh, 'FAMILY0000000000000000000A', 'app', self::ADA, [], null, $first, [], $first, $second);
        $token->rotate($first);
        $token->rotate($second);
        $token->revoke($first);
        $token->revoke($second);
        self::assertSame($first, $this->read($token, 'rotatedAt'));
        self::assertSame($first, $this->read($token, 'revokedAt'));

        $client = Client::register('app', 'App', null, ['https://a/cb'], [], [], false, $first);
        $client->disable($first);
        $client->disable($second);
        self::assertSame($first, $this->read($client, 'disabledAt'));
    }

    public function testAClientKeepsDistinctListsAndTheSecretHash(): void
    {
        $now = new DateTimeImmutable();
        $client = Client::register('app', 'App', null, ['https://a/cb', 'https://a/cb', 'https://b/cb'], ['https://a/out', 'https://a/out', 'https://b/out'], ['email', 'email', 'openid'], false, $now);

        self::assertSame(['https://a/cb', 'https://b/cb'], $client->redirectUris());
        self::assertSame(['https://a/out', 'https://b/out'], $client->postLogoutRedirectUris());
        self::assertSame(['openid', 'email'], $client->scopes());
        self::assertSame(['openid', 'email'], Client::register('app', 'App', null, ['https://a/cb'], [], ['openid', 'email'], false, $now)->scopes());
        self::assertSame(hash('sha256', 'secret'), Client::hash('secret'));

        $client->rename(str_repeat('é', 100));
        self::assertSame(str_repeat('é', 100), $client->name());
        $client->rename(' Renamed ');
        self::assertSame('Renamed', $client->name());

        $this->expectException(InvalidValue::class);

        $client->rename(str_repeat('é', 101));
    }

    public function testAConsentKeepsDistinctScopes(): void
    {
        $now = new DateTimeImmutable();
        $consent = Consent::grant(self::ADA, 'app', ['openid', 'openid', 'email'], $now);
        self::assertSame(['openid', 'email'], $consent->scopes());

        $consent->extend(['phone', 'profile', 'email'], $now);
        self::assertSame(['openid', 'email', 'phone', 'profile'], $consent->scopes());
        $consent->extend(['email', 'openid', 'address'], $now);
        self::assertSame(['openid', 'email', 'phone', 'profile', 'address'], $consent->scopes());
        self::assertSame(hash('sha256', self::ADA."\0app"), Consent::idOf(self::ADA, 'app'));
        self::assertSame(Consent::idOf(self::ADA, 'app'), $consent->id());
    }

    public function testALinkedIdentityIsKeyedByItsProviderAndItsSubject(): void
    {
        self::assertSame(hash('sha256', "google\0g-1"), LinkedIdentity::idOf('google', 'g-1'));
        self::assertNotSame(LinkedIdentity::idOf('google', 'g-1'), LinkedIdentity::idOf('google', 'g-2'));
        self::assertNotSame(LinkedIdentity::idOf('google', 'g-1'), LinkedIdentity::idOf('github', 'g-1'));
        self::assertSame('google', new ExternalIdentity('google', str_repeat('a', 255))->provider);
        self::assertSame('google', LinkedIdentity::link(self::ADA, 'google', str_repeat('a', 255), new DateTimeImmutable())->provider());
    }

    public function testAPasskeyNameIsCountedInLetters(): void
    {
        $passkey = Passkey::register(self::ADA, new RegisteredCredential('id', 'cose', 0, str_repeat("\x01", 16), false, false, false), str_repeat('é', 100), new DateTimeImmutable());
        self::assertSame(str_repeat('é', 100), $passkey->name());
    }

    public function testASessionKeepsDistinctMethods(): void
    {
        $session = AccountSession::open(str_repeat('a', 43), self::ADA, 'Device', ['pwd', 'pwd', 'otp'], false, new DateTimeImmutable());
        self::assertSame(['pwd', 'otp'], $session->methods());

        $session->addMethods(['hwk', 'user', 'hwk', 'pwd']);
        self::assertSame(['pwd', 'otp', 'hwk', 'user'], $session->methods());
        $session->addMethods(['pwd', 'mfa']);
        self::assertSame(['pwd', 'otp', 'hwk', 'user', 'mfa'], $session->methods());
    }

    public function testAKeyKeepsTheFirstMomentOfEachStep(): void
    {
        $first = new DateTimeImmutable('2026-11-14 10:00:00');
        $second = $first->modify('+1 hour');
        $key = SigningKey::create('kid', SigningAlgorithm::ES256, 'p', ['kid' => 'kid'], $first);
        $key->activate($first);
        $key->activate($second);
        $key->retire($first);
        $key->retire($second);

        self::assertSame($first, $this->read($key, 'activatedAt'));
        self::assertSame($first, $key->retiredAt());
    }

    public function testTheLockoutDoublesUpToAFixedExponent(): void
    {
        $policy = new LockoutPolicy(1, 1, 4_000_000_000);
        $last = new DateTimeImmutable('2026-11-14 10:00:00');

        self::assertSame($last->getTimestamp() + 2 ** 30, $policy->lockedUntil(100, $last)?->getTimestamp());
        self::assertSame($last->getTimestamp() + 2 ** 30, $policy->lockedUntil(31, $last)?->getTimestamp());
        self::assertSame($last->getTimestamp() + 2 ** 29, $policy->lockedUntil(30, $last)?->getTimestamp());
    }

    public function testRecoveryCodesUseTheWholeAlphabetInTwoGroupsOfFive(): void
    {
        $codes = new RecoveryCodes(str_repeat('k', 32))->generate(4_000);
        $counts = [];
        $overlap = 0;

        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^['.RecoveryCodes::ALPHABET.']{5}-['.RecoveryCodes::ALPHABET.']{5}$/', $code);
            $overlap += $code[4] === $code[6] ? 1 : 0;

            foreach (str_split(str_replace('-', '', $code)) as $letter) {
                $counts[$letter] = ($counts[$letter] ?? 0) + 1;
            }
        }

        $letters = array_map(strval(...), array_keys($counts));
        sort($letters, \SORT_STRING);
        $expected = str_split(RecoveryCodes::ALPHABET);
        sort($expected, \SORT_STRING);
        self::assertSame($expected, $letters);
        // 40 000 letters over 31: about 1 290 each; a letter drawn twice as often, or never, is a different alphabet.
        self::assertNotSame([], $counts);
        self::assertLessThan(1_800, max($counts));
        self::assertGreaterThan(900, min($counts));
        self::assertLessThan(400, $overlap);
    }

    public function testTheCodeOfTheAuthenticatorApplicationAcceptsOnePeriodOfDrift(): void
    {
        $totp = Totp::fromBase32(Base32::encode($this->secret()));
        $now = 1_000_000_000;

        self::assertNotNull($totp->verify($totp->at($now - 30), $now));
        self::assertNotNull($totp->verify($totp->at($now + 30), $now));
        self::assertNull($totp->verify($totp->at($now - 60), $now));
        self::assertNull($totp->verify($totp->at($now + 60), $now));
        self::assertNull($totp->verify('12345', $now));
        self::assertNull($totp->verify('abcdef', $now));
    }

    public function testTheEarliestCounterOfAWindowWins(): void
    {
        // The code of the periods 0 and 1 is the same for this secret: the earliest one is the counter accepted.
        $totp = Totp::fromBase32(Base32::encode($this->secret()), 1);

        self::assertSame('417211', $totp->at(0));
        self::assertSame('417211', $totp->at(30));
        self::assertSame(0, $totp->verify('417211', 30));
    }

    public function testTheParserSaysWhichParameterIsGivenTwice(): void
    {
        foreach ([['foo' => ['a']], ['foo' => ['a', 'b']]] as $extra) {
            try {
                $this->parse($extra);
                self::fail('Accepted.');
            } catch (AuthorizationError $authorizationError) {
                self::assertSame('The parameter "foo" is given more than once.', $authorizationError->description);
                self::assertTrue($authorizationError->isRedirectable());
            }
        }

        self::assertSame('app', $this->parse(['foo' => null])->clientId);
    }

    public function testTheParserAcceptsNothingAfterTheMaximumAge(): void
    {
        self::assertSame(5, $this->parse(['max_age' => '5'])->maxAge);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessageIsOrContains('The maximum age is a number of seconds.');

        $this->parse(['max_age' => "5\n"]);
    }

    public function testTheScopesAreAListOfThoseKnown(): void
    {
        self::assertSame(['openid'], $this->parse(['scope' => 'bogus openid'])->scopes);
        self::assertSame(['openid', 'email'], $this->parse(['scope' => 'bogus openid email phone'])->scopes);
    }

    public function testAParameterMayHold2048BytesAndNotMore(): void
    {
        self::assertSame(str_repeat('n', 2048), $this->parse(['nonce' => str_repeat('n', 2048)])->nonce);

        try {
            $this->parse(['client_id' => str_repeat('a', 2048)]);
            self::fail('Accepted.');
        } catch (AuthorizationError $authorizationError) {
            self::assertSame('The client is unknown.', $authorizationError->description);
        }

        try {
            $this->parse(['redirect_uri' => str_repeat('a', 2048)]);
            self::fail('Accepted.');
        } catch (AuthorizationError $authorizationError) {
            self::assertSame('The redirect address is not one the client registered.', $authorizationError->description);
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    private function parse(array $query): AuthorizationRequest
    {
        $client = Client::register('app', 'App', 'secret', ['https://app.example/cb'], [], ['email', 'profile'], false, new DateTimeImmutable());
        $query += [
            'response_type' => 'code',
            'client_id' => 'app',
            'redirect_uri' => 'https://app.example/cb',
            'scope' => 'openid',
            'code_challenge' => Pkce::challengeOf('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'),
            'code_challenge_method' => 'S256',
        ];

        return new AuthorizationRequestParser(['openid', 'profile', 'email', 'phone'])->parse($query, static fn (string $id): ?Client => 'app' === $id ? $client : null);
    }

    /**
     * A secret whose codes of the first two periods are the same.
     */
    private function secret(): string
    {
        $secret = hex2bin('b8ad2f63dad8d09839ceeb21b80d7ce360a6cd64');
        self::assertIsString($secret);

        return $secret;
    }

    private function read(object $object, string $property): mixed
    {
        return new ReflectionProperty($object, $property)->getValue($object);
    }
}
