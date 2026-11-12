<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Phil\IdentityClientBundle\Application\Identity\VerifiedIdentity;
use Phil\IdentityClientBundle\Infrastructure\Security\IdentityUserProvider;
use Phil\IdentityClientBundle\Infrastructure\Security\StatelessUserProvisioner;
use Phil\IdentityClientBundle\Tests\Functional\App\RecordingProvisioner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\OidcUser;

#[CoversClass(IdentityUserProvider::class)]
#[CoversClass(StatelessUserProvisioner::class)]
final class IdentityUserProviderTest extends TestCase
{
    public function testAttributesProvisionTheUser(): void
    {
        $provisioner = new RecordingProvisioner();
        $provider = new IdentityUserProvider($provisioner, 'https://id.example.test', ['name']);

        $user = $provider->loadUserByIdentifier('s1', ['sub' => 's1', 'name' => 'Ada', 'other' => 'x']);

        self::assertSame('s1', $user->getUserIdentifier());
        self::assertEquals(VerifiedIdentity::fromClaims('https://id.example.test', 's1', ['name' => 'Ada'], ['name']), $provisioner->last);
    }

    public function testNoAttributesLooksTheUserUp(): void
    {
        $provisioner = new RecordingProvisioner();
        $provider = new IdentityUserProvider($provisioner, 'i', []);

        self::assertSame('s1', $provider->loadUserByIdentifier('s1')->getUserIdentifier());
        self::assertNull($provisioner->last);
    }

    public function testAMissingUserIsNotFound(): void
    {
        $provisioner = new RecordingProvisioner();
        $provisioner->exists = false;

        $provider = new IdentityUserProvider($provisioner, 'i', []);

        try {
            $provider->refreshUser(new InMemoryUser('s1', null));
            self::fail('The user should be missing.');
        } catch (UserNotFoundException $userNotFoundException) {
            self::assertSame('s1', $userNotFoundException->getUserIdentifier());
        }
    }

    public function testRefreshReloadsTheUser(): void
    {
        $provider = new IdentityUserProvider(new RecordingProvisioner(), 'i', []);

        self::assertSame('s1', $provider->refreshUser(new InMemoryUser('s1', null))->getUserIdentifier());
        self::assertTrue($provider->supportsClass(InMemoryUser::class));
    }

    public function testTheDefaultUserKnowsOnlyItsSubject(): void
    {
        $provisioner = new StatelessUserProvisioner();

        $user = $provisioner->provision(VerifiedIdentity::fromClaims('i', 's1', ['name' => 'Ada'], ['name']));
        $found = $provisioner->find('s2');

        self::assertInstanceOf(OidcUser::class, $user);
        self::assertSame('s1', $user->getUserIdentifier());
        self::assertSame('s1', $user->getSub());
        self::assertNull($user->getName());
        self::assertSame('s2', $found->getUserIdentifier());
        self::assertSame(['ROLE_USER'], $user->getRoles());
    }
}
