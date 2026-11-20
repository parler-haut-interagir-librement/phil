<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationPolicy;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationType;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryPasskeyRepository;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\ArrayChallengeStore;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Authenticator;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Cbor;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Passkeys::class)]
final class PasskeysTest extends TestCase
{
    private Services $services;

    private ArrayChallengeStore $challenges;

    private Passkeys $passkeys;

    protected function setUp(): void
    {
        $this->services = new Services();
        $store = new InMemoryPasskeyRepository();
        $this->challenges = new ArrayChallengeStore();
        $this->passkeys = new Passkeys(new RelyingParty('example.com', 'Example', ['https://example.com']), $store, $this->services->accounts, $this->challenges, $this->services->journal, $this->services->clock);
    }

    public function testRegistrationThenSignIn(): void
    {
        $account = $this->services->account();
        $authenticator = new Authenticator();
        $options = $this->passkeys->registrationOptions($account);

        self::assertIsString($options['challenge']);
        self::assertSame(['id' => Base64Url::encode($account->id()), 'name' => 'ada@example.com', 'displayName' => 'Ada'], $options['user']);
        self::assertSame([], $options['excludeCredentials']);
        $passkey = $this->passkeys->register($account, $authenticator->register($options['challenge']), ' Phone ');

        self::assertSame('Phone', $passkey->name());
        self::assertEquals([$passkey], $this->passkeys->of($account));
        self::assertSame([IdentityEventType::PasskeyAdded->value, $account->id(), ['passkey' => $passkey->id(), 'aaguid' => $passkey->aaguid(), 'backup_eligible' => false, 'synced' => false, 'attestation' => 'none']], array_last($this->services->audit->records));
        self::assertSame([], $this->challenges->values);

        $again = $this->passkeys->registrationOptions($account);
        self::assertSame([['type' => 'public-key', 'id' => Base64Url::encode($authenticator->credentialId)]], $again['excludeCredentials']);

        $options = $this->passkeys->signInOptions();
        self::assertSame([], $options['allowCredentials']);
        self::assertIsString($options['challenge']);
        [$signedIn, $verified] = $this->passkeys->signIn($authenticator->sign($options['challenge'], $account->id()));

        self::assertEquals($account, $signedIn);
        self::assertTrue($verified);
        $used = $this->passkeys->of($account)[0];
        self::assertSame(1, $used->signCount());
        self::assertSame($this->services->clock->now()->getTimestamp(), $used->lastUsedAt()?->getTimestamp());

        $options = $this->passkeys->signInOptions($account);
        self::assertSame([['type' => 'public-key', 'id' => Base64Url::encode($authenticator->credentialId)]], $options['allowCredentials']);
        self::assertIsString($options['challenge']);
        self::assertEquals($account, $this->passkeys->signIn($authenticator->sign($options['challenge']), $account)[0]);
    }

    public function testAPasskeyBreakingTheAttestationPolicyIsNotAccepted(): void
    {
        $store = new InMemoryPasskeyRepository();
        $passkeys = new Passkeys(new RelyingParty('example.com', 'Example', ['https://example.com'], attestation: new AttestationPolicy('direct', backup: 'forbidden')), $store, $this->services->accounts, $this->challenges, $this->services->journal, $this->services->clock);
        $account = $this->services->account();
        $synced = new Authenticator(flags: 0x0D);
        $options = $passkeys->registrationOptions($account);
        self::assertIsString($options['challenge']);
        self::assertSame('direct', $options['attestation']);

        try {
            $passkeys->register($account, $synced->register($options['challenge']), 'Synced');
            self::fail('Accepted.');
        } catch (Refused $refused) {
            self::assertSame(['passkey.not_accepted'], $refused->reasons);
        }

        $stored = static fn (): array => self::stored($store, $account->id());
        self::assertSame([], $stored());
        $bound = new Authenticator();
        $options = $passkeys->registrationOptions($account);
        self::assertIsString($options['challenge']);
        $passkey = $passkeys->register($account, $bound->registerAttested($options['challenge'], static fn (string $signed, Authenticator $self): array => ['alg' => -7, 'sig' => Cbor::bytes($self->signature($signed))], 'packed', str_repeat("\x07", 16)), 'Bound');
        self::assertSame(['packed', AttestationType::Self, '07070707-0707-0707-0707-070707070707', false], [$passkey->attestationFormat(), $passkey->attestationType(), $passkey->aaguid(), $passkey->isBackupEligible()]);
        self::assertSame(['passkey' => $passkey->id(), 'aaguid' => '07070707-0707-0707-0707-070707070707', 'backup_eligible' => false, 'synced' => false, 'attestation' => 'self'], $this->services->audit->last()[2]);
        self::assertSame([AttestationType::Self], array_map(static fn (Passkey $stored): AttestationType => $stored->attestationType(), $stored()));
    }

    /**
     * @return list<Passkey>
     *
     * @phpstan-impure
     */
    private static function stored(InMemoryPasskeyRepository $store, string $accountId): array
    {
        return $store->forAccount($accountId);
    }

    public function testAChallengeIsForOneAnswerAndOneAccount(): void
    {
        $account = $this->services->account();
        $bob = $this->services->account('bob@example.com');
        $authenticator = new Authenticator();
        $options = $this->passkeys->registrationOptions($account);
        self::assertIsString($options['challenge']);

        $this->assertRefused('passkey.expired', fn (): Passkey => $this->passkeys->register($bob, $authenticator->register($options['challenge']), 'x'));
        $this->assertRefused('passkey.expired', fn (): Passkey => $this->passkeys->register($account, $authenticator->register($options['challenge']), 'x'));
        $this->challenges->put('passkey.register', $account->id().' ');
        $this->assertRefused('passkey.expired', fn (): Passkey => $this->passkeys->register($account, $authenticator->register(''), 'x'));
        $this->challenges->put('passkey.register', $account->id());
        $this->assertRefused('passkey.expired', fn (): Passkey => $this->passkeys->register($account, $authenticator->register(''), 'x'));
    }

    public function testRegistrationRefusals(): void
    {
        $account = $this->services->account();
        $authenticator = new Authenticator();
        $register = function (callable|string $json, string $name = 'Phone') use ($account): void {
            $options = $this->passkeys->registrationOptions($account);
            self::assertIsString($options['challenge']);
            $this->passkeys->register($account, \is_string($json) ? $json : $json($options['challenge']), $name);
        };
        $answer = $authenticator->register(...);

        $this->assertRefused('passkey.refused', static fn () => $register($authenticator->register('other')));
        $this->assertRefused('passkey.refused', static fn () => $register('{'));
        $this->assertRefused('passkey.refused', static fn () => $register(str_repeat(' ', 32_769)));
        $this->assertRefused('passkey.refused', static fn () => $register('{"type":"public-key"}'));
        $this->assertRefused('passkey.refused', static fn () => $register('{"type":"other","response":{}}'));
        $this->assertRefused('passkey.refused', static fn () => $register('{"type":"public-key","response":{"clientDataJSON":"","attestationObject":"AA"}}'));
        $this->assertRefused('passkey.refused', static fn () => $register('{"type":"public-key","response":{"clientDataJSON":"!","attestationObject":"AA"}}'));
        $this->assertRefused('passkey.name_invalid', static fn () => $register($answer, ''));
        $register($answer);
        $this->assertRefused('passkey.known', static fn () => $register($answer));
    }

    public function testSignInRefusals(): void
    {
        $account = $this->services->account();
        $bob = $this->services->account('bob@example.com');
        $authenticator = new Authenticator();
        $options = $this->passkeys->registrationOptions($account);
        self::assertIsString($options['challenge']);
        $this->passkeys->register($account, $authenticator->register($options['challenge']), 'Phone');
        $signIn = function (callable $json, ?Account $expected = null, ?Account $optionsFor = null): void {
            $options = $this->passkeys->signInOptions($optionsFor ?? $expected);
            self::assertIsString($options['challenge']);
            $answer = $json($options['challenge']);
            self::assertIsString($answer);
            $this->passkeys->signIn($answer, $expected);
        };

        $this->assertRefused('passkey.refused', static fn () => $signIn(static fn (string $c): string => new Authenticator()->sign($c)));
        $this->assertRefused('passkey.refused', static fn () => $signIn(static fn (string $c): string => $authenticator->sign($c, $bob->id())));
        $this->assertRefused('passkey.refused', static fn () => $signIn(static fn (string $c): string => $authenticator->sign('other')));
        $this->assertRefused('passkey.refused', static fn () => $signIn(static fn (string $c): string => $authenticator->sign($c), $bob));
        $this->assertRefused('passkey.refused', static fn () => $signIn(static fn (string $c): string => '{"type":"public-key","rawId":"!","response":{}}'));
        $this->assertRefused('passkey.refused', static fn () => $signIn(static fn (string $c): string => '{"type":"public-key","response":{}}'));
        $this->assertRefused('passkey.expired', static fn () => $signIn(static fn (string $c): string => $authenticator->sign($c), $account, $bob));

        $account->disable($this->services->clock->now());
        $this->services->accounts->save($account);
        $this->assertRefused('passkey.refused', static fn () => $signIn(static fn (string $c): string => $authenticator->sign($c)));
    }

    public function testRenameAndRemove(): void
    {
        $account = $this->services->account();
        $first = new Authenticator();
        $second = new Authenticator();

        foreach ([$first, $second] as $authenticator) {
            $options = $this->passkeys->registrationOptions($account);
            self::assertIsString($options['challenge']);
            $this->passkeys->register($account, $authenticator->register($options['challenge']), 'Key');
        }

        [$one, $two] = $this->passkeys->of($account);
        $this->passkeys->rename($account, $one->id(), 'Laptop');
        self::assertSame('Laptop', $this->passkeys->of($account)[0]->name());
        $this->assertRefused('passkey.name_invalid', fn () => $this->passkeys->rename($account, $one->id(), ''));
        $this->assertRefused('passkey.unknown', fn () => $this->passkeys->rename($account, 'nope', 'x'));
        $this->assertRefused('passkey.unknown', fn () => $this->passkeys->remove($this->services->account('bob@example.com'), $one->id()));

        $account->changePassword(null, $this->services->clock->now());
        $this->services->accounts->save($account);
        $this->passkeys->remove($account, $one->id());
        self::assertEquals([$two], $this->passkeys->of($account));
        self::assertSame([IdentityEventType::PasskeyRemoved->value, $account->id(), ['passkey' => $one->id()]], array_last($this->services->audit->records));
        $this->assertRefused('passkey.last', fn () => $this->passkeys->remove($account, $two->id()));

        $account->changePassword('hash', $this->services->clock->now());
        $this->services->accounts->save($account);
        $this->passkeys->remove($account, $two->id());
        self::assertSame([], $this->passkeys->of($account));
    }

    private function assertRefused(string $reason, callable $operation): void
    {
        try {
            $operation();
            self::fail('Accepted, expected '.$reason);
        } catch (Refused $refused) {
            self::assertSame([$reason], $refused->reasons);
        }
    }
}
