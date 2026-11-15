<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Application;

use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentities;
use Phil\IdentityProviderBundle\Application\ExternalIdentity;
use Phil\IdentityProviderBundle\Application\ExternalSignIn;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Passkey\Passkey;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryLinkedIdentityRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryPasskeyRepository;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Services;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExternalIdentities::class)]
#[CoversClass(ExternalIdentity::class)]
#[CoversClass(ExternalSignIn::class)]
final class ExternalIdentitiesTest extends TestCase
{
    private Services $services;

    private InMemoryLinkedIdentityRepository $linked;

    private InMemoryPasskeyRepository $passkeys;

    protected function setUp(): void
    {
        $this->services = new Services();
        $this->linked = new InMemoryLinkedIdentityRepository();
        $this->passkeys = new InMemoryPasskeyRepository();
    }

    public function testAnUnknownIdentityCreatesALinkedAccount(): void
    {
        $signIn = $this->identities()->resolve(new ExternalIdentity('google', 'sub-1', 'Ada@Example.com', true, 'Ada'));

        self::assertTrue($signIn->created);
        self::assertTrue($signIn->linked);
        self::assertSame('ada@example.com', $signIn->account->email());
        self::assertSame('Ada', $signIn->account->displayName());
        self::assertTrue($signIn->account->isEmailVerified());
        self::assertFalse($signIn->account->hasPassword());
        self::assertSame($signIn->account->id(), $this->linked->find('google', 'sub-1')?->accountId());
        self::assertSame([IdentityEventType::IdentityLinked->value, $signIn->account->id(), ['provider' => 'google']], array_last($this->services->audit->records));

        $again = $this->identities()->resolve(new ExternalIdentity('google', 'sub-1'));
        self::assertEquals($signIn->account, $again->account);
        self::assertFalse($again->created);
        self::assertFalse($again->linked);
        self::assertNotNull($this->linked->find('google', 'sub-1')?->lastUsedAt());

        $unverified = $this->identities()->resolve(new ExternalIdentity('github', 'g-1', 'bob@example.com'));
        self::assertFalse($unverified->account->isEmailVerified());
    }

    public function testAnAddressAlreadyKnownIsNeverMergedByDefault(): void
    {
        $account = $this->services->account();
        $account->verifyEmail($this->services->clock->now());

        $this->services->accounts->save($account);

        $this->assertRefused('external.email_known', fn (): ExternalSignIn => $this->identities()->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com', true)));
        $this->assertRefused('external.email_known', fn (): ExternalSignIn => $this->identities(linkVerifiedEmail: true)->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com', false)));
        self::assertSame([], $this->linked->forAccount($account->id()));

        $signIn = $this->identities(linkVerifiedEmail: true)->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com', true));
        self::assertEquals($account, $signIn->account);
        self::assertTrue($signIn->linked);
        self::assertFalse($signIn->created);
    }

    public function testAVerifiedAddressLinksOnlyToAnAccountThatVerifiedItAndIsEnabled(): void
    {
        $account = $this->services->account();
        $this->assertRefused('external.email_known', fn (): ExternalSignIn => $this->identities(linkVerifiedEmail: true)->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com', true)));
        $account->verifyEmail($this->services->clock->now());
        $account->disable($this->services->clock->now());

        $this->services->accounts->save($account);
        $this->assertRefused('external.email_known', fn (): ExternalSignIn => $this->identities(linkVerifiedEmail: true)->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com', true)));
    }

    public function testSomeoneSignedInLinksToTheirOwnAccount(): void
    {
        $account = $this->services->account();
        $bob = $this->services->account('bob@example.com');
        $signIn = $this->identities()->resolve(new ExternalIdentity('google', 'sub-1', 'other@example.com'), $account);

        self::assertEquals($account, $signIn->account);
        self::assertTrue($signIn->linked);
        self::assertEquals($account, $this->identities()->resolve(new ExternalIdentity('google', 'sub-1'), $account)->account);
        $this->assertRefused('external.linked_elsewhere', fn (): ExternalSignIn => $this->identities()->resolve(new ExternalIdentity('google', 'sub-1'), $bob));

        $account->disable($this->services->clock->now());
        $this->services->accounts->save($account);
        $this->assertRefused('external.linked_elsewhere', fn (): ExternalSignIn => $this->identities()->resolve(new ExternalIdentity('google', 'sub-1')));
        $this->services->accounts->remove($account);
        $this->assertRefused('external.linked_elsewhere', fn (): ExternalSignIn => $this->identities()->resolve(new ExternalIdentity('google', 'sub-1')));
    }

    public function testClosedRegistration(): void
    {
        $this->assertRefused('external.registration_closed', fn (): ExternalSignIn => $this->identities(register: false)->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com')));
        $this->assertRefused('external.registration_closed', fn (): ExternalSignIn => $this->identities()->resolve(new ExternalIdentity('google', 'sub-1')));
    }

    public function testUnlinkKeepsAWayToSignIn(): void
    {
        $account = $this->identities()->resolve(new ExternalIdentity('google', 'sub-1', 'ada@example.com'))->account;
        [$google] = $this->identities()->of($account);

        $this->assertRefused('external.last', fn () => $this->identities()->unlink($account, $google->id()));
        $this->assertRefused('external.unknown', fn () => $this->identities()->unlink($account, 'nope'));
        $this->assertRefused('external.last', fn () => $this->identities(passkeys: false)->unlink($account, $google->id()));

        $this->identities()->resolve(new ExternalIdentity('github', 'g-1'), $account);
        $this->identities()->unlink($account, $google->id());
        self::assertCount(1, $this->identities()->of($account));
        self::assertSame([IdentityEventType::IdentityUnlinked->value, $account->id(), ['provider' => 'google']], array_last($this->services->audit->records));

        [$github] = $this->identities()->of($account);
        $this->passkeys->save(Passkey::register($account->id(), new RegisteredCredential('c', 'k', 0, str_repeat("\0", 16), false, false, false), 'Key', $this->services->clock->now()));
        $this->identities()->unlink($account, $github->id());
        self::assertSame([], $this->identities()->of($account));
    }

    public function testAnAccountWithAPasswordUnlinksItsLastIdentity(): void
    {
        $account = $this->services->account();
        $this->identities()->resolve(new ExternalIdentity('google', 'sub-1'), $account);
        [$google] = $this->identities()->of($account);
        $this->identities(passkeys: false)->unlink($account, $google->id());

        self::assertSame([], $this->identities()->of($account));
    }

    public function testAnExternalIdentityIsWellFormed(): void
    {
        foreach ([['Google', 's'], ['google', ''], ['google', str_repeat('s', 256)]] as [$provider, $subject]) {
            try {
                new ExternalIdentity($provider, $subject);
                self::fail($provider);
            } catch (InvalidValue $refusal) {
                self::assertNotSame('', $refusal->getMessage());
            }
        }

        $identity = new ExternalIdentity('google', 's');
        self::assertSame([null, false, null], [$identity->email, $identity->emailVerified, $identity->displayName]);
    }

    private function identities(bool $linkVerifiedEmail = false, bool $register = true, bool $passkeys = true): ExternalIdentities
    {
        return new ExternalIdentities($this->linked, $this->services->accounts, $passkeys ? $this->passkeys : null, $this->services->accounts(), $this->services->journal, $this->services->clock, $linkVerifiedEmail, $register);
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
