<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit;

use Phil\AccessBundle\PhilAccessBundle;
use Phil\OrganizationsBundle\Application\Contract\MemberOfUser;
use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Application\DomainJoin;
use Phil\OrganizationsBundle\Application\InvitationPolicy;
use Phil\OrganizationsBundle\Application\Invitations;
use Phil\OrganizationsBundle\Application\MemberErasure;
use Phil\OrganizationsBundle\Application\Members;
use Phil\OrganizationsBundle\Application\OnBehalf;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\OwnershipCheck;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Application\Teams;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Domain\Event\MemberJoined;
use Phil\OrganizationsBundle\Domain\Event\OrganizationCreated;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Domain\ValueObject\Roles;
use Phil\OrganizationsBundle\Infrastructure\Access\MembershipTuples;
use Phil\OrganizationsBundle\Infrastructure\Access\OrganizationRelations;
use Phil\OrganizationsBundle\Infrastructure\Console\CheckOwnersCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\ListOrganizationsCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\PurgeInvitationsCommand;
use Phil\OrganizationsBundle\Infrastructure\Console\ShowOrganizationCommand;
use Phil\OrganizationsBundle\Infrastructure\Dbal\DbalOrganizationStore;
use Phil\OrganizationsBundle\Infrastructure\DependencyInjection\OrganizationsServices;
use Phil\OrganizationsBundle\Infrastructure\Doctrine\OrganizationSchemaListener;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\OrganizationsBundle\Infrastructure\Privacy\OrganizationsPrivacy;
use Phil\OrganizationsBundle\Infrastructure\Security\OrganizationVoter;
use Phil\OrganizationsBundle\Infrastructure\Security\UserIdentifierMember;
use Phil\OrganizationsBundle\PhilOrganizationsBundle;
use Phil\OrganizationsBundle\Tests\Fixtures\RecordingDispatcher;
use Phil\ResourcePermissions\RelationWriter;
use Phil\Slug\TransliteratingSlugger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilOrganizationsBundle::class)]
#[CoversClass(OrganizationsServices::class)]
final class PhilOrganizationsBundleTest extends TestCase
{
    private const string KEY = 'a-key-of-thirty-two-bytes-at-least-for-tests';

    public function testTheAlias(): void
    {
        self::assertSame('phil_organizations', $this->extension()->getAlias());
    }

    public function testTheDefaultsOnDbal(): void
    {
        $container = $this->load([]);

        self::assertSame(DbalOrganizationStore::class, (string) $container->getAlias(OrganizationStore::class));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DbalOrganizationStore::class)->getArguments());
        self::assertTrue($container->getDefinition(OrganizationSchemaListener::class)->hasTag('doctrine.event_listener'));
        self::assertSame(TransliteratingSlugger::class, $container->getDefinition(OrganizationsServices::SLUGGER)->getClass());
        self::assertSame(UserIdentifierMember::class, (string) $container->getAlias(MemberOfUser::class));
        self::assertTrue($container->getDefinition(OrganizationVoter::class)->hasTag('security.voter'));
        self::assertTrue($container->getDefinition(OrganizationsPrivacy::class)->hasTag('phil_privacy.exporter'));
        self::assertTrue($container->getDefinition(OrganizationsPrivacy::class)->hasTag('phil_privacy.eraser'));
        self::assertTrue($container->getDefinition(OnBehalf::class)->hasTag('kernel.reset'));
        self::assertEquals([7 * 86_400, 30 * 86_400, 1, 100], $container->getDefinition(InvitationPolicy::class)->getArguments());
        self::assertSame([self::KEY], $container->getDefinition(OrganizationsServices::HASHER)->getArguments());
        self::assertSame([false, ['member']], \array_slice($container->getDefinition(DomainJoin::class)->getArguments(), 4));
        self::assertSame([2], \array_slice($container->getDefinition(OwnershipCheck::class)->getArguments(), 1));
        self::assertSame(30, $container->getDefinition(PurgeInvitationsCommand::class)->getArgument(1));
        self::assertFalse($container->hasDefinition(OrganizationRelations::class));

        foreach ([ListOrganizationsCommand::class, ShowOrganizationCommand::class, PurgeInvitationsCommand::class, CheckOwnersCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }
    }

    public function testTheServicesWorkOnMemory(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'roles' => ['billing' => ['permissions' => ['billing.read']], 'admin' => ['permissions' => ['billing.read']]],
            'team_roles' => ['reviewer' => ['permissions' => ['review.approve']]],
            'domain_join' => ['enabled' => true, 'roles' => ['member', 'billing']],
            'recommended_owners' => 3,
        ]]);
        $events = new RecordingDispatcher();
        $container->register('clock')->setSynthetic(true);
        $container->register('event_dispatcher')->setSynthetic(true);
        $container->compile(true);
        $container->set('clock', new MockClock('2026-11-17 10:00:00'));
        $container->set('event_dispatcher', $events);

        $organizations = $container->get(Organizations::class);
        self::assertInstanceOf(Organizations::class, $organizations);
        $acme = $organizations->create('Acme', MemberId::fromString('ann'));
        self::assertSame([OrganizationCreated::class, MemberJoined::class], $events->classes());
        self::assertInstanceOf(InMemoryOrganizationStore::class, $container->get(OrganizationStore::class));

        $catalog = $container->get(RoleCatalog::class);
        self::assertInstanceOf(RoleCatalog::class, $catalog);
        self::assertContains('billing.read', $catalog->permissionsOf(Roles::of(['admin'])));
        self::assertSame(['owner', 'admin', 'member', 'billing'], $catalog->names());

        $ownership = $container->get(OwnershipCheck::class);
        self::assertInstanceOf(OwnershipCheck::class, $ownership);
        self::assertSame(3, $ownership->recommended);

        $join = $container->get(DomainJoin::class);
        self::assertInstanceOf(DomainJoin::class, $join);
        self::assertTrue($join->isEnabled());

        $teams = $container->get(Teams::class);
        self::assertInstanceOf(Teams::class, $teams);
        $team = $teams->create($acme->id, MemberId::fromString('ann'), 'Design');
        self::assertSame(['member', 'reviewer'], $teams->addMember($team->id, MemberId::fromString('ann'), MemberId::fromString('ann'), ['reviewer', 'member'])->roles->names);

        foreach ([Members::class, Invitations::class, OnBehalf::class] as $service) {
            self::assertInstanceOf($service, $container->get($service));
        }
    }

    public function testAnotherStoreSluggerAndMemberOfUser(): void
    {
        $container = $this->load([['store' => 'app.store', 'slugger' => 'app.slugger', 'member_of_user' => 'app.members', 'clock' => 'app.clock']]);

        self::assertSame('app.store', (string) $container->getAlias(OrganizationStore::class));
        self::assertSame('app.slugger', (string) $container->getAlias(OrganizationsServices::SLUGGER));
        self::assertSame('app.members', (string) $container->getAlias(MemberOfUser::class));
        self::assertFalse($container->hasDefinition(DbalOrganizationStore::class));
        self::assertFalse($container->hasDefinition(UserIdentifierMember::class));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(Members::class)->getArgument(3));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(ShowOrganizationCommand::class)->getArgument(3));
    }

    public function testTheConnection(): void
    {
        self::assertEquals([new Reference('doctrine.dbal.other_connection')], $this->load([['connection' => 'other']])->getDefinition(DbalOrganizationStore::class)->getArguments());
    }

    public function testTheInvitationLimits(): void
    {
        $container = $this->load([['invitations' => ['hash_key' => ['new-'.self::KEY, self::KEY], 'default_lifetime' => 2, 'max_lifetime' => 3, 'default_uses' => 4, 'max_uses' => 5, 'purge_retention' => 0]]]);

        self::assertEquals([2 * 86_400, 3 * 86_400, 4, 5], $container->getDefinition(InvitationPolicy::class)->getArguments());
        self::assertSame(['new-'.self::KEY, self::KEY], $container->getDefinition(OrganizationsServices::HASHER)->getArguments());
        self::assertSame(0, $container->getDefinition(PurgeInvitationsCommand::class)->getArgument(1));
    }

    public function testTheTuplesWhenAccessIsOn(): void
    {
        $container = $this->load([['access' => ['enabled' => true, 'organization_kind' => 'guild', 'team_kind' => 'squad']]]);

        self::assertTrue($container->getDefinition(OrganizationRelations::class)->hasTag('phil_access.model_contributor'));
        self::assertSame(['guild', 'squad'], \array_slice($container->getDefinition(OrganizationRelations::class)->getArguments(), 2));
        $listeners = $container->getDefinition(MembershipTuples::class)->getTag('kernel.event_listener');
        self::assertCount(8, $listeners);
        self::assertContains(['event' => MemberJoined::class, 'method' => 'onMemberJoined'], $listeners);
    }

    public function testTheTuplesFollowTheAccessBundleByDefault(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->accessExtension());
        $this->extension()->load([['invitations' => ['hash_key' => self::KEY]]], $container);
        self::assertTrue($container->hasDefinition(OrganizationRelations::class));

        $off = new ContainerBuilder();
        $off->registerExtension($this->accessExtension());
        $this->extension()->load([['invitations' => ['hash_key' => self::KEY], 'access' => ['enabled' => false]]], $off);
        self::assertFalse($off->hasDefinition(OrganizationRelations::class));
        self::assertFalse($off->hasDefinition(MembershipTuples::class));
    }

    public function testTheSmallestAndTheLargestValuesAreTaken(): void
    {
        $container = $this->load([['recommended_owners' => 1, 'invitations' => ['default_lifetime' => 1, 'max_lifetime' => 1, 'default_uses' => 1, 'max_uses' => 1, 'purge_retention' => 36_500]]]);

        self::assertEquals([86_400, 86_400, 1, 1], $container->getDefinition(InvitationPolicy::class)->getArguments());
        self::assertSame(36_500, $container->getDefinition(PurgeInvitationsCommand::class)->getArgument(1));
        self::assertSame(1, $container->getDefinition(OwnershipCheck::class)->getArgument(1));

        $same = $this->load([['invitations' => ['default_lifetime' => 30, 'max_lifetime' => 30, 'default_uses' => 100, 'max_uses' => 100]]]);
        self::assertEquals([30 * 86_400, 30 * 86_400, 100, 100], $same->getDefinition(InvitationPolicy::class)->getArguments());
    }

    public function testTheServicesAreWiredToEachOther(): void
    {
        $container = $this->load([['access' => ['enabled' => true]]]);
        $store = new Reference(OrganizationStore::class);
        $access = new Reference(OrganizationAccess::class);

        foreach ([OrganizationAccess::class, Organizations::class, Members::class, Teams::class, Invitations::class, DomainJoin::class, OnBehalf::class, OwnershipCheck::class, MemberErasure::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
        }

        self::assertTrue($container->getAlias(OrganizationStore::class)->isPublic());
        self::assertEquals([$store, new Reference(UnitOfWork::class), new Reference('clock')], $container->getDefinition(MemberErasure::class)->getArguments());
        self::assertEquals([[$access, new Reference('clock'), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)]], [$container->getDefinition(OnBehalf::class)->getArguments()]);
        self::assertSame([['method' => 'reset']], $container->getDefinition(OnBehalf::class)->getTag('kernel.reset'));
        self::assertSame([['event' => 'postGenerateSchema']], $container->getDefinition(OrganizationSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(OrganizationSchemaListener::class)->getArguments());
        self::assertTrue($container->hasDefinition(UserIdentifierMember::class));
        self::assertEquals([$access, new Reference(MemberOfUser::class)], $container->getDefinition(OrganizationVoter::class)->getArguments());
        self::assertEquals([new Reference(Organizations::class)], $container->getDefinition(ListOrganizationsCommand::class)->getArguments());
        self::assertEquals([new Reference(OwnershipCheck::class)], $container->getDefinition(CheckOwnersCommand::class)->getArguments());
        self::assertEquals([$store, new Reference(MemberErasure::class)], $container->getDefinition(OrganizationsPrivacy::class)->getArguments());
        self::assertEquals([new Reference(RelationWriter::class), new Reference(OrganizationRelations::class)], $container->getDefinition(MembershipTuples::class)->getArguments());
    }

    public function testTheMemoryStoreAndAnotherStoreAreResetAndPublic(): void
    {
        $memory = $this->load([['store' => 'memory']]);

        self::assertSame([['method' => 'reset']], $memory->getDefinition(InMemoryOrganizationStore::class)->getTag('kernel.reset'));
        self::assertTrue($memory->getAlias(OrganizationStore::class)->isPublic());
        self::assertTrue($this->load([['store' => 'app.store']])->getAlias(OrganizationStore::class)->isPublic());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'no invitations' => [[], 'invitations'];
        yield 'no hash key' => [['invitations' => ['default_uses' => 1]], 'hash_key'];
        yield 'empty hash key' => [['invitations' => ['hash_key' => []]], 'hash_key'];
        yield 'owner declared' => [['roles' => ['owner' => ['permissions' => []]]], 'other than owner'];
        yield 'role out of shape' => [['roles' => ['Billing' => ['permissions' => []]]], 'A role is 1 to 64'];
        yield 'team role named organization' => [['team_roles' => ['organization' => ['permissions' => []]]], 'other than organization'];
        yield 'permission out of shape' => [['roles' => ['billing' => ['permissions' => ['Billing Read']]]], 'A permission is 1 to 128'];
        yield 'default lifetime past the longest' => [['invitations' => ['hash_key' => self::KEY, 'default_lifetime' => 31]], 'at most its longest lifetime'];
        yield 'default uses past the most' => [['invitations' => ['hash_key' => self::KEY, 'default_uses' => 101]], 'at most its longest lifetime and its most uses'];
        yield 'too many uses' => [['invitations' => ['hash_key' => self::KEY, 'max_uses' => 10_001]], 'max_uses'];
        yield 'owner by domain' => [['domain_join' => ['roles' => ['owner']]], 'never grants the owner role'];
        yield 'no recommended owner' => [['recommended_owners' => 0], 'recommended_owners'];
        yield 'role with a hyphen' => [['roles' => ['my-role' => ['permissions' => []]]], 'A role is 1 to 64'];
        yield 'team role with a hyphen' => [['team_roles' => ['my-role' => ['permissions' => []]]], 'A role is 1 to 64'];
        yield 'no lifetime' => [['invitations' => ['hash_key' => self::KEY, 'default_lifetime' => 0]], 'too small'];
        yield 'no longest lifetime' => [['invitations' => ['hash_key' => self::KEY, 'max_lifetime' => 0]], 'too small'];
        yield 'no use' => [['invitations' => ['hash_key' => self::KEY, 'default_uses' => 0]], 'too small'];
        yield 'no most uses' => [['invitations' => ['hash_key' => self::KEY, 'max_uses' => 0]], 'too small'];
        yield 'negative retention' => [['invitations' => ['hash_key' => self::KEY, 'purge_retention' => -1]], 'too small'];
        yield 'retention past a century' => [['invitations' => ['hash_key' => self::KEY, 'purge_retention' => 36_501]], 'too big'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testAnInvalidConfigurationIsRefused(array $config, string $message): void
    {
        if (!\array_key_exists('invitations', $config) && 'invitations' !== $message) {
            $config['invitations'] = ['hash_key' => self::KEY];
        }

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($message, '/').'/');

        $this->extension()->load([$config], new ContainerBuilder());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['invitations' => ['hash_key' => self::KEY]], ...$configs], $container);

        return $container;
    }

    private function accessExtension(): ExtensionInterface
    {
        $extension = new PhilAccessBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilOrganizationsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
