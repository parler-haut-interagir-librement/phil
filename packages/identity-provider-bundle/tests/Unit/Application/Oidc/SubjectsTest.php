<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application\Oidc;

use DateTimeImmutable;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Oidc\Authorization;
use Phil\IdentityProviderBundle\Application\Oidc\ClientRegistry;
use Phil\IdentityProviderBundle\Application\Oidc\LogoutTokens;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Subjects;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Application\Oidc\UserClaims;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\Client;
use Phil\IdentityProviderBundle\Domain\Oidc\Pkce;
use Phil\IdentityProviderBundle\Domain\Oidc\SubjectType;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryClientRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryGrantRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemorySigningKeyRepository;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Query;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * The `sub` of an account: its internal identifier, an opaque identifier the same for every client, or a pairwise one
 * per sector — the same in every token and answer a client receives.
 */
#[CoversClass(Subjects::class)]
#[CoversClass(Tokens::class)]
#[CoversClass(ProviderSettings::class)]
#[CoversClass(ClientRegistry::class)]
#[CoversClass(Client::class)]
#[CoversClass(LogoutTokens::class)]
final class SubjectsTest extends TestCase
{
    private const string ISSUER = 'https://id.example';

    private const string SALT = 'a salt of at least thirty-two bytes!';

    private const string VERIFIER = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';

    private Services $services;

    private InMemoryClientRepository $clients;

    private InMemoryGrantRepository $grants;

    private InMemorySigningKeyRepository $keyStore;

    private ProviderSettings $settings;

    protected function setUp(): void
    {
        $this->services = new Services();
        $this->clients = new InMemoryClientRepository();
        $this->grants = new InMemoryGrantRepository();
        $this->keyStore = new InMemorySigningKeyRepository();
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256);
    }

    public function testTheSubjectOfEachType(): void
    {
        [$app] = $this->registry()->register('app', 'App', ['https://app.example/cb', 'https://app.example/other']);
        [$admin] = $this->registry()->register('admin', 'Admin', ['https://admin.example/cb'], sectorIdentifier: 'app.example');
        [$other] = $this->registry()->register('other', 'Other', ['https://other.example/cb']);
        [$spread] = $this->registry()->register('spread', 'Spread', ['https://one.example/cb', 'https://two.example/cb']);
        self::assertSame(['app.example', 'app.example', 'other.example', 'spread'], [$app->sector(), $admin->sector(), $other->sector(), $spread->sector()]);

        self::assertSame('ACCOUNT', new Subjects($this->settings)->of('ACCOUNT', $app));

        $opaque = new Subjects(new ProviderSettings(self::ISSUER, subjectType: SubjectType::Opaque, subjectSalt: self::SALT));
        $expected = Base64Url::encode(hash_hmac('sha256', "public\0\0ACCOUNT", self::SALT, true));
        self::assertSame([$expected, $expected], [$opaque->of('ACCOUNT', $app), $opaque->of('ACCOUNT', $other)]);
        self::assertNotSame($expected, $opaque->of('OTHER', $app));

        $pairwise = new Subjects(new ProviderSettings(self::ISSUER, subjectType: SubjectType::Pairwise, subjectSalt: self::SALT));
        self::assertSame(Base64Url::encode(hash_hmac('sha256', "pairwise\0app.example\0ACCOUNT", self::SALT, true)), $pairwise->of('ACCOUNT', $app));
        self::assertSame($pairwise->of('ACCOUNT', $app), $pairwise->of('ACCOUNT', $admin), 'One sector, one subject.');
        self::assertNotSame($pairwise->of('ACCOUNT', $app), $pairwise->of('ACCOUNT', $other));
        self::assertNotSame($pairwise->of('ACCOUNT', $app), $opaque->of('ACCOUNT', $app));
        self::assertNotSame($pairwise->of('ACCOUNT', $app), new Subjects(new ProviderSettings(self::ISSUER, subjectType: SubjectType::Pairwise, subjectSalt: self::SALT.'.'))->of('ACCOUNT', $app));
    }

    public function testAnOpaqueSubjectNeedsASaltOfThirtyTwoBytes(): void
    {
        self::assertSame(str_repeat('s', 32), new ProviderSettings(self::ISSUER, subjectType: SubjectType::Pairwise, subjectSalt: str_repeat('s', 32))->subjectSalt);

        foreach ([[SubjectType::Opaque, null], [SubjectType::Pairwise, str_repeat('s', 31)]] as [$type, $salt]) {
            try {
                new ProviderSettings(self::ISSUER, subjectType: $type, subjectSalt: $salt);
                self::fail('Accepted '.$type->value);
            } catch (InvalidValue $invalidValue) {
                self::assertStringContainsString('32 bytes', $invalidValue->getMessage());
            }
        }
    }

    public function testASectorIdentifierIsAHostInLowerCase(): void
    {
        $now = new DateTimeImmutable('2026-11-14 10:00:00');

        foreach (['App.example', 'app.example/', '', '-app.example', 'app_example', str_repeat('a', 64).'.example'] as $sector) {
            try {
                Client::register('app', 'App', 'secret', ['https://a/cb'], [], [], false, $now, sectorIdentifier: $sector);
                self::fail('Accepted '.$sector);
            } catch (InvalidValue) {
                self::assertNotSame('x', $sector);
            }
        }

        self::assertSame('a.b-c.example', Client::register('app', 'App', 'secret', ['https://a/cb'], [], [], false, $now, sectorIdentifier: 'a.b-c.example')->sector());
        self::assertSame('app', Client::register('app', 'App', 'secret', ['https://a/cb'], [], [], false, $now)->sector());
    }

    public function testEveryTokenAndAnswerOfAClientCarriesItsSubject(): void
    {
        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256, subjectType: SubjectType::Pairwise, subjectSalt: self::SALT);
        [$client] = $this->registry()->register('app', 'App', ['https://app.example/cb'], [], ['offline_access']);
        $account = $this->services->account();
        $tokens = $this->tokens();
        $subject = new Subjects($this->settings)->of($account->id(), $client);
        self::assertNotSame($account->id(), $subject);

        $response = $tokens->exchangeCode($client, $this->code($account), 'https://app.example/cb', self::VERIFIER);
        $keys = $this->keys()->publicKey(...);
        self::assertSame($subject, Jws::decode((string) $response['id_token'], $keys)['sub']);
        self::assertSame($subject, Jws::decode((string) $response['access_token'], $keys)['sub']);
        self::assertSame($subject, $tokens->introspect($client, (string) $response['access_token'])['sub']);
        self::assertSame($subject, $tokens->introspect($client, (string) $response['refresh_token'])['sub']);
        self::assertSame($subject, new UserClaims()->of($account, ['openid'], 'app', $tokens->subject($account->id(), 'app'))['sub']);
        self::assertSame($subject, Jws::decode(new LogoutTokens($this->keys(), $this->settings, $this->services->clock)->issue($client, $account->id(), 'sid'), $keys)['sub']);

        self::assertSame($subject, $tokens->subject($account->id(), 'app'));
        self::assertNull($tokens->subject($account->id(), 'unknown'));
        self::assertNull($tokens->subject($account->id(), null));

        $this->settings = new ProviderSettings(self::ISSUER, SigningAlgorithm::ES256);
        self::assertSame($account->id(), $this->tokens()->subject($account->id(), 'unknown'));
        self::assertSame($account->id(), $this->tokens()->subject($account->id(), null));
    }

    private function code(Account $account): string
    {
        $authorization = new Authorization($this->clients, $this->grants, $this->settings, $this->services->clock, $this->services->journal);
        $request = $authorization->parse(['response_type' => 'code', 'client_id' => 'app', 'redirect_uri' => 'https://app.example/cb', 'scope' => 'openid offline_access', 'code_challenge' => Pkce::challengeOf(self::VERIFIER), 'code_challenge_method' => 'S256']);

        return Query::get($authorization->issueCode($request, $account, $this->services->clock->now(), [], 'sid'), 'code');
    }

    private function keys(): SigningKeys
    {
        return new SigningKeys($this->keyStore, new PlainSecretCipher(), $this->settings, $this->services->clock, new LockFactory(new InMemoryStore()), $this->services->journal);
    }

    private function registry(): ClientRegistry
    {
        return new ClientRegistry($this->clients, $this->services->clock, $this->settings, $this->services->journal);
    }

    private function tokens(): Tokens
    {
        return new Tokens($this->clients, $this->grants, $this->services->accounts, $this->keys(), $this->settings, $this->services->clock, $this->services->journal);
    }
}
