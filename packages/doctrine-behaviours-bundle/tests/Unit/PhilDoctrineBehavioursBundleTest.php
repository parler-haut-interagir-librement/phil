<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Tests\Unit;

use LogicException;
use Phil\DoctrineBehaviours\BlameEraser;
use Phil\DoctrineBehaviours\BlameListener;
use Phil\DoctrineBehaviours\Contract\BlameResolver;
use Phil\DoctrineBehaviours\Contract\OwnerResolver;
use Phil\DoctrineBehaviours\Contract\Publishable;
use Phil\DoctrineBehaviours\Contract\Versionable;
use Phil\DoctrineBehaviours\Contract\VersionRestoreGuard;
use Phil\DoctrineBehaviours\DatedIdentifierListener;
use Phil\DoctrineBehaviours\DeactivatedFilter;
use Phil\DoctrineBehaviours\DeactivationListener;
use Phil\DoctrineBehaviours\Exception\InvalidOwner;
use Phil\DoctrineBehaviours\OwnerEraser;
use Phil\DoctrineBehaviours\OwnerId;
use Phil\DoctrineBehaviours\OwnerListener;
use Phil\DoctrineBehaviours\PositionListener;
use Phil\DoctrineBehaviours\Positions;
use Phil\DoctrineBehaviours\PublicationListener;
use Phil\DoctrineBehaviours\SlugListener;
use Phil\DoctrineBehaviours\SoftDeletedFilter;
use Phil\DoctrineBehaviours\SoftDeleteListener;
use Phil\DoctrineBehaviours\TimestampListener;
use Phil\DoctrineBehaviours\TranslationListener;
use Phil\DoctrineBehaviours\Translations;
use Phil\DoctrineBehaviours\Tree;
use Phil\DoctrineBehaviours\TreeListener;
use Phil\DoctrineBehaviours\Version\RecordVersion;
use Phil\DoctrineBehaviours\VersionListener;
use Phil\DoctrineBehaviours\Versions;
use Phil\DoctrineBehavioursBundle\Infrastructure\DependencyInjection\BehaviourServices;
use Phil\DoctrineBehavioursBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\BlamePrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Privacy\OwnerPrivacy;
use Phil\DoctrineBehavioursBundle\Infrastructure\Security\SecurityBlameResolver;
use Phil\DoctrineBehavioursBundle\Infrastructure\Security\SecurityOwnerResolver;
use Phil\DoctrineBehavioursBundle\Infrastructure\Security\SecurityVersionRestoreGuard;
use Phil\DoctrineBehavioursBundle\Infrastructure\Security\UserMethod;
use Phil\DoctrineBehavioursBundle\PhilDoctrineBehavioursBundle;
use Phil\Slug\Sluggers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(PhilDoctrineBehavioursBundle::class)]
#[CoversClass(BehaviourServices::class)]
#[CoversClass(BinaryKeysListener::class)]
#[CoversClass(SecurityBlameResolver::class)]
#[CoversClass(SecurityOwnerResolver::class)]
#[CoversClass(SecurityVersionRestoreGuard::class)]
#[CoversClass(UserMethod::class)]
final class PhilDoctrineBehavioursBundleTest extends TestCase
{
    public function testEveryBehaviourIsOnByDefault(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_doctrine_behaviours', $this->extension()->getAlias());
        self::assertSame(['service_container', BinaryKeysListener::class, TimestampListener::class, BlameResolver::class, BlameListener::class, BlameEraser::class, SoftDeleteListener::class, TreeListener::class, Tree::class, DeactivationListener::class, PositionListener::class, Positions::class, TranslationListener::class, Translations::class, BlamePrivacy::class], array_keys($container->getDefinitions()));
        self::assertSame(['doctrine.event_listener' => [['event' => 'loadClassMetadata']]], $container->getDefinition(BinaryKeysListener::class)->getTags());
        self::assertSame([['event' => 'prePersist'], ['event' => 'onFlush']], $container->getDefinition(TimestampListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference('clock')], $container->getDefinition(TimestampListener::class)->getArguments());
        self::assertSame([['event' => 'prePersist'], ['event' => 'onFlush']], $container->getDefinition(BlameListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference(BlameResolver::class)], $container->getDefinition(BlameListener::class)->getArguments());
        self::assertSame(SecurityBlameResolver::class, $container->getDefinition(BlameResolver::class)->getClass());
        self::assertEquals([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE), null], $container->getDefinition(BlameResolver::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(BlameEraser::class)->getArguments());
        self::assertTrue($container->getDefinition(BlameEraser::class)->isPublic());
        self::assertSame([['method' => 'reset']], $container->getDefinition(TreeListener::class)->getTag('kernel.reset'));
        self::assertSame([['event' => 'onFlush']], $container->getDefinition(SoftDeleteListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference('clock')], $container->getDefinition(SoftDeleteListener::class)->getArguments());
        self::assertSame([['event' => 'postPersist'], ['event' => 'onFlush'], ['event' => 'postFlush']], $container->getDefinition(TreeListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(Tree::class)->getArguments());
        self::assertTrue($container->getDefinition(Tree::class)->isPublic());
        self::assertSame([['event' => 'prePersist'], ['event' => 'onFlush']], $container->getDefinition(DeactivationListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference('clock')], $container->getDefinition(DeactivationListener::class)->getArguments());
        self::assertSame([['event' => 'onFlush']], $container->getDefinition(PositionListener::class)->getTag('doctrine.event_listener'));
        self::assertSame([], $container->getDefinition(PositionListener::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(Positions::class)->getArguments());
        self::assertTrue($container->getDefinition(Positions::class)->isPublic());
        self::assertSame([['event' => 'loadClassMetadata']], $container->getDefinition(TranslationListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(Translations::class)->getArguments());
        self::assertTrue($container->getDefinition(Translations::class)->isPublic());
        self::assertEquals([new Reference(BlameEraser::class), null], $container->getDefinition(BlamePrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(BlamePrivacy::class)->getTags());
        self::assertSame('phil_soft_deleted', BehaviourServices::FILTER);
        self::assertSame('phil_deactivated', BehaviourServices::DEACTIVATED_FILTER);
    }

    public function testEveryBehaviourCanBeOff(): void
    {
        self::assertSame(['service_container', BinaryKeysListener::class], array_keys($this->load(['timestamps' => false, 'blame' => false, 'soft_delete' => false, 'tree' => false, 'deactivation' => false, 'position' => false, 'translation' => false])->getDefinitions()));
        self::assertFalse($this->load(['soft_delete' => ['enabled' => false]])->hasDefinition(SoftDeleteListener::class));
        self::assertSame([false, false], [$this->load(['blame' => ['enabled' => false]])->hasDefinition(BlameListener::class), $this->load(['blame' => false])->hasDefinition(BlameEraser::class)]);
        self::assertSame('uuid', $this->load(['blame' => ['user_method' => 'uuid']])->getDefinition(BlameResolver::class)->getArgument(1));
        self::assertTrue($this->load(['blame' => true])->hasDefinition(BlameListener::class));
        self::assertTrue($this->load(['soft_delete' => ['filter' => false]])->hasDefinition(SoftDeleteListener::class));
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>, bool}>
     */
    public static function filterConfigurations(): iterable
    {
        yield 'nothing' => [[], true];
        yield 'another key' => [[['tree' => false]], true];
        yield 'off' => [[['soft_delete' => false]], false];
        yield 'off then on' => [[['soft_delete' => false], ['soft_delete' => true]], true];
        yield 'on then off' => [[['soft_delete' => true], ['soft_delete' => false]], false];
        yield 'a map' => [[['soft_delete' => []]], true];
        yield 'a map, not enabled' => [[['soft_delete' => ['enabled' => false]]], false];
        yield 'a map, enabled' => [[['soft_delete' => false], ['soft_delete' => ['enabled' => true]]], true];
        yield 'a map, off and kept off' => [[['soft_delete' => false], ['soft_delete' => ['filter' => true]]], false];
        yield 'without the filter' => [[['soft_delete' => ['filter' => false]]], false];
        yield 'without the filter, kept' => [[['soft_delete' => ['filter' => false]], ['soft_delete' => ['enabled' => true]]], false];
        yield 'without the filter, then with' => [[['soft_delete' => ['filter' => false]], ['soft_delete' => ['filter' => true]]], true];
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    #[DataProvider('filterConfigurations')]
    public function testTheFilterIsAddedAsTheConfigurationsSay(array $configs, bool $added): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'doctrine';
            }
        });
        $extension = $this->extension();
        $container->registerExtension($extension);

        foreach ($configs as $config) {
            $container->loadFromExtension('phil_doctrine_behaviours', $config);
        }

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        /** @var list<array{orm: array{filters: array<string, array{class: string, enabled: bool}>}}> $prepended */
        $prepended = $container->getExtensionConfig('doctrine');
        self::assertSame($added ? ['class' => SoftDeletedFilter::class, 'enabled' => true] : null, $prepended[0]['orm']['filters'][BehaviourServices::FILTER] ?? null);
    }

    public function testWithoutDoctrineNoFilterIsAdded(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertFalse($container->hasExtension('doctrine'));
    }

    public function testNothingIsAddedWhenNoBehaviourNeedsIt(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->fakeExtension('doctrine'));
        $container->registerExtension($this->fakeExtension('framework'));

        $extension = $this->extension();
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_doctrine_behaviours', ['soft_delete' => false, 'deactivation' => false]);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([[], []], [$container->getExtensionConfig('doctrine'), $container->getExtensionConfig('framework')]);
    }

    public function testTheDeactivatedFilterIsAddedOrLeftOut(): void
    {
        $full = ['filters' => ['phil_soft_deleted' => ['class' => SoftDeletedFilter::class, 'enabled' => true], 'phil_deactivated' => ['class' => DeactivatedFilter::class, 'enabled' => true]]];
        self::assertSame([$full], $this->prepended(['versions' => false], 'doctrine'));
        self::assertSame([['filters' => ['phil_soft_deleted' => ['class' => SoftDeletedFilter::class, 'enabled' => true]]]], $this->prepended(['deactivation' => ['filter' => false]], 'doctrine'));
        self::assertSame([['filters' => ['phil_soft_deleted' => ['class' => SoftDeletedFilter::class, 'enabled' => true]]]], $this->prepended(['deactivation' => false], 'doctrine'));
        self::assertSame([['filters' => ['phil_deactivated' => ['class' => DeactivatedFilter::class, 'enabled' => true]]]], $this->prepended(['soft_delete' => false], 'doctrine'));
    }

    public function testVersionsAddTheirMapping(): void
    {
        /** @var array{mappings: array<string, array<string, mixed>>} $orm */
        $orm = $this->prepended(['versions' => true], 'doctrine')[0];

        self::assertEquals(['type' => 'attribute', 'is_bundle' => false, 'dir' => \dirname((string) new ReflectionClass(RecordVersion::class)->getFileName()), 'prefix' => 'Phil\DoctrineBehaviours\Version'], $orm['mappings']['PhilDoctrineBehaviours']);
    }

    public function testTheWorkflowIsDeclaredUnlessTheApplicationHasItsOwn(): void
    {
        self::assertSame([], $this->prepended([], 'framework'));
        self::assertSame([], $this->prepended(['publication' => ['define_workflow' => false]], 'framework'));
        self::assertSame([BehaviourServices::workflow('articles')], $this->prepended(['publication' => ['workflow' => 'articles']], 'framework', false));
        self::assertSame(['workflows' => ['enabled' => true, 'workflows' => ['articles' => [
            'type' => 'state_machine',
            'marking_store' => ['type' => 'method', 'property' => 'publicationState'],
            'supports' => [Publishable::class],
            'initial_marking' => ['draft'],
            'places' => ['draft', 'published', 'unpublished'],
            'transitions' => [
                'publish' => ['from' => ['draft', 'unpublished'], 'to' => 'published'],
                'unpublish' => ['from' => ['published'], 'to' => 'unpublished'],
            ],
        ]]]], BehaviourServices::workflow('articles'));
    }

    public function testWithoutFrameworkNoWorkflowIsDeclared(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_doctrine_behaviours', ['publication' => true]);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertFalse($container->hasExtension('framework'));
    }

    public function testTheOptionalBehavioursAreOffUntilAsked(): void
    {
        $container = $this->load([]);

        foreach ([OwnerListener::class, OwnerResolver::class, OwnerEraser::class, PublicationListener::class, SlugListener::class, Versions::class, VersionListener::class, VersionRestoreGuard::class, DatedIdentifierListener::class] as $id) {
            self::assertFalse($container->hasDefinition($id), $id);
        }
    }

    public function testThePrivacyAnswersFollowTheBlameAndTheOwner(): void
    {
        $container = $this->load(['owner' => ['user_method' => 'uuid'], 'privacy' => ['blame_replacement' => 'former-member', 'owner_replacement' => 'former']]);

        self::assertEquals([new Reference(BlameEraser::class), 'former-member'], $container->getDefinition(BlamePrivacy::class)->getArguments());
        self::assertEquals([new Reference(OwnerEraser::class), new Definition(OwnerId::class, ['former'])], $container->getDefinition(OwnerPrivacy::class)->getArguments());
        self::assertSame(['phil_privacy.exporter' => [[]], 'phil_privacy.eraser' => [[]]], $container->getDefinition(OwnerPrivacy::class)->getTags());
        self::assertEquals([new Reference(OwnerEraser::class), null], $this->load(['owner' => ['user_method' => 'uuid']])->getDefinition(OwnerPrivacy::class)->getArguments());

        $without = $this->load(['blame' => false, 'owner' => ['user_method' => 'uuid'], 'privacy' => false]);
        self::assertSame([false, false], [$without->hasDefinition(BlamePrivacy::class), $without->hasDefinition(OwnerPrivacy::class)]);
        self::assertFalse($this->load(['blame' => false])->hasDefinition(BlamePrivacy::class));
        self::assertFalse($this->load([])->hasDefinition(OwnerPrivacy::class));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidPseudonyms(): iterable
    {
        yield 'an empty blame' => [['blame_replacement' => ''], 'The pseudonym of the blame'];
        yield 'a blame too long' => [['blame_replacement' => str_repeat('a', 256)], 'The pseudonym of the blame'];
        yield 'a blame with a newline' => [['blame_replacement' => "former\n"], 'The pseudonym of the blame'];
        yield 'a blame not a string' => [['blame_replacement' => 12], 'The pseudonym of the blame'];
        yield 'an empty owner' => [['owner_replacement' => ''], 'The pseudonym of the owner'];
        yield 'an owner too long' => [['owner_replacement' => str_repeat('a', 256)], 'The pseudonym of the owner'];
        yield 'an owner with an @' => [['owner_replacement' => 'former@example.org'], 'The pseudonym of the owner'];
        yield 'an owner not a string' => [['owner_replacement' => 12], 'The pseudonym of the owner'];
    }

    /**
     * @param array<string, mixed> $privacy
     */
    #[DataProvider('invalidPseudonyms')]
    public function testAPseudonymIsChecked(array $privacy, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);
        $this->load(['privacy' => $privacy]);
    }

    public function testPseudonymsAtTheirLimitsAreKept(): void
    {
        $container = $this->load(['owner' => ['user_method' => 'uuid'], 'privacy' => ['blame_replacement' => str_repeat('a', 255), 'owner_replacement' => str_repeat('b', 255)]]);

        self::assertSame(str_repeat('a', 255), $container->getDefinition(BlamePrivacy::class)->getArgument(1));
    }

    public function testTheOwnerNeedsAMethod(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_doctrine_behaviours.owner.user_method is required');
        $this->load(['owner' => true]);
    }

    public function testAnOwnerMethodIsAMethodName(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The user method of the owner is the name of a method');
        $this->load(['owner' => ['user_method' => 'get id']]);
    }

    public function testTheOwnerServices(): void
    {
        $container = $this->load(['owner' => ['user_method' => 'uuid']]);

        self::assertSame(SecurityOwnerResolver::class, $container->getDefinition(OwnerResolver::class)->getClass());
        self::assertEquals([new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'uuid'], $container->getDefinition(OwnerResolver::class)->getArguments());
        self::assertEquals([new Reference(OwnerResolver::class)], $container->getDefinition(OwnerListener::class)->getArguments());
        self::assertSame([['event' => 'prePersist']], $container->getDefinition(OwnerListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(OwnerEraser::class)->getArguments());
        self::assertTrue($container->getDefinition(OwnerEraser::class)->isPublic());
    }

    public function testThePublicationListenerFollowsTheWorkflowNamed(): void
    {
        $definition = $this->load(['publication' => ['workflow' => 'articles']])->getDefinition(PublicationListener::class);

        self::assertSame([['event' => 'workflow.articles.entered', 'method' => '__invoke']], $definition->getTag('kernel.event_listener'));
        self::assertEquals([new Reference('clock')], $definition->getArguments());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badWorkflowNames(): iterable
    {
        yield 'a space' => ['a b'];
        yield 'a line' => ["articles\n"];
        yield 'a capital' => ['Articles'];
        yield 'a number first' => ['1a'];
        yield 'not a text' => [12];
    }

    #[DataProvider('badWorkflowNames')]
    public function testAWorkflowHasAName(mixed $name): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The name of the publication workflow is lowercase letters');
        $this->load(['publication' => ['workflow' => $name]]);
    }

    public function testASlugTakesItsSluggersFromTheSlugBundle(): void
    {
        $container = $this->load(['slug' => true]);

        self::assertEquals([new Reference(Sluggers::class)], $container->getDefinition(SlugListener::class)->getArguments());
        self::assertSame([['event' => 'onFlush']], $container->getDefinition(SlugListener::class)->getTag('doctrine.event_listener'));
    }

    public function testTheSlugBundleIsAllItNeeds(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->fakeExtension('phil_slug'));

        $extension = $this->extension();
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_doctrine_behaviours', ['slug' => true]);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertTrue($container->hasExtension('phil_slug'));
    }

    public function testTheSlugSaysWhatItLacks(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('composer require phil/slug-bundle');
        $this->prepended(['slug' => true], 'doctrine');
    }

    public function testTheVersionServices(): void
    {
        $container = $this->load(['versions' => ['restore_right' => 'ROLE_EDITOR_IN_CHIEF']]);

        self::assertSame(SecurityVersionRestoreGuard::class, $container->getDefinition(VersionRestoreGuard::class)->getClass());
        self::assertEquals([new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), 'ROLE_EDITOR_IN_CHIEF'], $container->getDefinition(VersionRestoreGuard::class)->getArguments());
        self::assertSame([['event' => 'onFlush']], $container->getDefinition(VersionListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([new Reference('doctrine.orm.entity_manager'), new Reference('clock'), new Reference(VersionRestoreGuard::class)], $container->getDefinition(Versions::class)->getArguments());
        self::assertTrue($container->getDefinition(Versions::class)->isPublic());
        self::assertSame('PHIL_VERSION_RESTORE', $this->load(['versions' => true])->getDefinition(VersionRestoreGuard::class)->getArgument(1));
    }

    public function testARightHasAName(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load(['versions' => ['restore_right' => '']]);
    }

    public function testTheDatedIdentifierService(): void
    {
        $definition = $this->load(['dated_identifier' => true])->getDefinition(DatedIdentifierListener::class);

        self::assertEquals([new Reference('clock')], $definition->getArguments());
        self::assertSame([['event' => 'prePersist']], $definition->getTag('doctrine.event_listener'));
    }

    public function testAnOwnerIsTheUserOfTheTokenByTheMethodNamed(): void
    {
        $tokens = new TokenStorage();
        $resolver = new SecurityOwnerResolver($tokens, 'getUserIdentifier');
        self::assertNull($resolver->current());
        self::assertNull(new SecurityOwnerResolver(null, 'getUserIdentifier')->current());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('uuid-1', null), 'main'));
        self::assertSame('uuid-1', new SecurityOwnerResolver($tokens, 'getUserIdentifier')->current()?->value);

        $tokens->setToken(new NullToken());
        self::assertNull($resolver->current());
    }

    public function testAMethodGivingNothingNamesNobody(): void
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new class implements UserInterface {
            public function nothing(): ?string
            {
                return [] === $this->getRoles() ? null : 'never';
            }

            public function getRoles(): array
            {
                return [];
            }

            public function getUserIdentifier(): string
            {
                return 'x';
            }
        }, 'main'));

        self::assertNull(new SecurityOwnerResolver($tokens, 'nothing')->current());
    }

    public function testAnOwnerUnderSwitchUserIsTheAccountActedAs(): void
    {
        $tokens = new TokenStorage();
        $admin = new UsernamePasswordToken(new InMemoryUser('admin', null), 'main');
        $tokens->setToken(new SwitchUserToken(new InMemoryUser('ada', null), 'main', [], $admin));

        self::assertSame('ada', new SecurityOwnerResolver($tokens, 'getUserIdentifier')->current()?->value);
    }

    public function testAnEMailIsNoOwner(): void
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.com', null), 'main'));

        $this->expectException(InvalidOwner::class);
        new SecurityOwnerResolver($tokens, 'getUserIdentifier')->current();
    }

    public function testAMissingOwnerMethodSaysWhatItIsFor(): void
    {
        $tokens = new TokenStorage();
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null), 'main'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('The owner names a user by');
        new SecurityOwnerResolver($tokens, 'uuid')->current();
    }

    public function testGoingBackTakesTheRightWithTheRecordAsSubject(): void
    {
        $entity = self::createStub(Versionable::class);
        $asked = [];
        $checker = self::createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(static function (mixed $right, mixed $subject) use (&$asked, $entity): bool {
            $asked[] = [$right, $subject === $entity];

            return 'RIGHT' === $right;
        });

        new SecurityVersionRestoreGuard($checker, 'RIGHT')->check($entity, 1);
        self::assertSame([['RIGHT', true]], $asked);
        self::assertSame('PHIL_VERSION_RESTORE', SecurityVersionRestoreGuard::DEFAULT_RIGHT);

        try {
            new SecurityVersionRestoreGuard($checker, 'OTHER')->check($entity, 7);
            self::fail('Granted.');
        } catch (AccessDeniedException $accessDeniedException) {
            self::assertSame('Going back to version 7 takes the right OTHER.', $accessDeniedException->getMessage());
            self::assertSame(['OTHER'], $accessDeniedException->getAttributes());
            self::assertSame($entity, $accessDeniedException->getSubject());
        }
    }

    public function testWithoutSecurityNobodyGoesBack(): void
    {
        $this->expectException(AccessDeniedException::class);
        new SecurityVersionRestoreGuard(null)->check(self::createStub(Versionable::class), 1);
    }

    public function testTheAuthenticatedUserIsBlamed(): void
    {
        $tokens = new TokenStorage();
        $resolver = new SecurityBlameResolver($tokens);

        self::assertNull($resolver->current());
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null), 'main'));
        self::assertSame('ada', $resolver->current());
        self::assertNull(new SecurityBlameResolver()->current());
    }

    public function testAUserIsNamedByTheMethodGiven(): void
    {
        $tokens = new TokenStorage();
        $member = new class implements UserInterface {
            public ?string $uuid = '0192f0a0-0000-7000-8000-000000000001';

            public function uuid(): ?string
            {
                return $this->uuid;
            }

            public function number(): int
            {
                return 42;
            }

            public function card(): Stringable
            {
                return new class implements Stringable {
                    public function __toString(): string
                    {
                        return 'card-7';
                    }
                };
            }

            public static function shared(): string
            {
                return 'everyone';
            }

            public function named(string $prefix): string
            {
                return $prefix;
            }

            /**
             * @return list<string>
             */
            public function roles(): array
            {
                return [];
            }

            public function getRoles(): array
            {
                return [];
            }

            public function getUserIdentifier(): string
            {
                return 'ada@example.com';
            }

            /** @phpstan-ignore method.unused */
            private function secret(): string
            {
                return 'hidden';
            }
        };
        $tokens->setToken(new UsernamePasswordToken($member, 'main'));

        self::assertSame(['0192f0a0-0000-7000-8000-000000000001', '42', 'card-7', 'ada@example.com'], [new SecurityBlameResolver($tokens, 'uuid')->current(), new SecurityBlameResolver($tokens, 'number')->current(), new SecurityBlameResolver($tokens, 'card')->current(), new SecurityBlameResolver($tokens)->current()]);
        $member->uuid = null;
        self::assertNull(new SecurityBlameResolver($tokens, 'uuid')->current());

        $absent = 'which does not exist as a public method without argument';

        foreach (['missing' => $absent, 'secret' => $absent, 'shared' => $absent, 'named' => $absent, 'roles' => 'which gives no string, number or Stringable'] as $method => $message) {
            try {
                new SecurityBlameResolver($tokens, $method)->current();
                self::fail($method);
            } catch (LogicException $logicException) {
                self::assertStringContainsString($message, $logicException->getMessage());
            }
        }
    }

    public function testAUserActingAsAnotherIsBlamedForWhatTheyDo(): void
    {
        $tokens = new TokenStorage();
        $admin = new UsernamePasswordToken(new InMemoryUser('admin', null), 'main');
        $tokens->setToken(new SwitchUserToken(new InMemoryUser('ada', null), 'main', [], $admin));

        self::assertSame('admin', new SecurityBlameResolver($tokens)->current());
    }

    public function testTheUserMethodIsAMethodName(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['blame' => ['user_method' => 'get id']]);
    }

    public function testTheUserMethodEndsWithItsName(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['blame' => ['user_method' => "uuid\n"]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<mixed> what was prepended to the extension: under its `orm` key for Doctrine
     */
    private function prepended(array $config, string $alias, bool $orm = true): array
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->fakeExtension('doctrine'));
        $container->registerExtension($this->fakeExtension('framework'));

        $extension = $this->extension();
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_doctrine_behaviours', $config);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
        $configs = $container->getExtensionConfig($alias);

        return 'doctrine' === $alias && $orm ? array_column($configs, 'orm') : $configs;
    }

    private function fakeExtension(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(
                private readonly string $alias,
            ) {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilDoctrineBehavioursBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
