<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit;

use Override;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\ReactionsBundle\Application\ConfiguredVisibility;
use Phil\ReactionsBundle\Application\Contract\CurrentMember;
use Phil\ReactionsBundle\Application\Contract\EmojiImages;
use Phil\ReactionsBundle\Application\Contract\MemberNames;
use Phil\ReactionsBundle\Application\Contract\ReactionAccess;
use Phil\ReactionsBundle\Application\Contract\ReactionSource;
use Phil\ReactionsBundle\Application\Contract\ReactionThrottle;
use Phil\ReactionsBundle\Application\Contract\VisibilityPolicy;
use Phil\ReactionsBundle\Application\DenyingReactionAccess;
use Phil\ReactionsBundle\Application\Event\ReactionAdded;
use Phil\ReactionsBundle\Application\Event\ReactionRemoved;
use Phil\ReactionsBundle\Application\NoCurrentMember;
use Phil\ReactionsBundle\Application\NoMemberNames;
use Phil\ReactionsBundle\Application\PlainEmojiImages;
use Phil\ReactionsBundle\Application\ReactionBoard;
use Phil\ReactionsBundle\Application\ReactionCatalog;
use Phil\ReactionsBundle\Application\Reactions;
use Phil\ReactionsBundle\Application\ReactionsPerMember;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\ReactionsBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\ReactionsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\ReactionsBundle\Infrastructure\DependencyInjection\ReactionsServices;
use Phil\ReactionsBundle\Infrastructure\Doctrine\DoctrineReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionController;
use Phil\ReactionsBundle\Infrastructure\Http\ReactionGuard;
use Phil\ReactionsBundle\Infrastructure\Media\MediaEmojiImages;
use Phil\ReactionsBundle\Infrastructure\Memory\InMemoryReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Messenger\EventsOnTheBus;
use Phil\ReactionsBundle\Infrastructure\Privacy\ReactionsPrivacy;
use Phil\ReactionsBundle\Infrastructure\RateLimiter\SymfonyThrottle;
use Phil\ReactionsBundle\Infrastructure\Security\AuthorizationCheckerAccess;
use Phil\ReactionsBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\ReactionsBundle\Infrastructure\Settings\SettingsVisibility;
use Phil\ReactionsBundle\Infrastructure\Twig\Components\ReactionsComponent;
use Phil\ReactionsBundle\Infrastructure\Twig\KitComponents;
use Phil\ReactionsBundle\Infrastructure\Twig\ReactionsTwig;
use Phil\ReactionsBundle\PhilReactionsBundle;
use Phil\SettingsBundle\Application\Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ArrayNode;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\TwigExtraBundle\DependencyInjection\TwigExtraExtension;

#[CoversClass(PhilReactionsBundle::class)]
#[CoversClass(ReactionsServices::class)]
#[CoversClass(CsrfProtectionPass::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilReactionsBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_reactions', $this->extension()->getAlias());
        self::assertSame(DoctrineReactionRepository::class, (string) $container->getAlias(ReactionRepository::class));
        self::assertTrue($container->getAlias(ReactionRepository::class)->isPublic());
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DoctrineReactionRepository::class)->getArguments());
        self::assertFalse($container->hasDefinition(InMemoryReactionRepository::class));
        self::assertSame(DenyingReactionAccess::class, (string) $container->getAlias(ReactionAccess::class));
        self::assertSame(NoCurrentMember::class, (string) $container->getAlias(CurrentMember::class));
        self::assertTrue($container->hasDefinition(NoCurrentMember::class));
        self::assertSame(NoMemberNames::class, (string) $container->getAlias(MemberNames::class));
        self::assertSame(ConfiguredVisibility::class, (string) $container->getAlias(VisibilityPolicy::class));
        self::assertSame(PlainEmojiImages::class, (string) $container->getAlias(EmojiImages::class));
        self::assertSame(SymfonyThrottle::class, (string) $container->getAlias(ReactionThrottle::class));
        self::assertSame(['everyone', 'everyone', 'nobody'], $container->getDefinition(ConfiguredVisibility::class)->getArguments());
        self::assertEquals([new Reference('limiter.phil_reactions'), new Reference('clock')], $container->getDefinition(SymfonyThrottle::class)->getArguments());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertFalse($container->hasDefinition(EventsOnTheBus::class));
        self::assertFalse($container->hasDefinition(SettingsVisibility::class));
        self::assertFalse($container->hasDefinition(MediaEmojiImages::class));
        self::assertTrue($container->getDefinition(Reactions::class)->isPublic());
        self::assertTrue($container->getDefinition(ReactionBoard::class)->isPublic());
        self::assertTrue($container->getDefinition(ReactionCatalog::class)->isPublic());
        self::assertSame([['method' => 'reset']], $container->getDefinition(ReactionBoard::class)->getTag('kernel.reset'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(ReactionCatalog::class)->getTag('kernel.reset'));
        self::assertEquals(new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition(Reactions::class)->getArgument(5));
        self::assertEquals(new Reference(ReactionsPerMember::class), $container->getDefinition(Reactions::class)->getArgument(6));
        self::assertSame([1], $container->getDefinition(ReactionsPerMember::class)->getArguments());
        self::assertEquals(new TaggedIteratorArgument('phil_reactions.source'), $container->getDefinition(ReactionCatalog::class)->getArgument(0));
        self::assertSame([], $container->getDefinition(ReactionCatalog::class)->getArgument(2));
        self::assertTrue($container->getAutoconfiguredInstanceof()[ReactionSource::class]->hasTag('phil_reactions.source'));
        self::assertTrue($container->getDefinition(ReactionController::class)->isPublic());
        self::assertEquals([new Reference('security.csrf.token_manager'), new Reference('uri_signer'), new Reference('router')], $container->getDefinition(ReactionGuard::class)->getArguments());
        self::assertEquals([new Reference(Reactions::class), new Reference(CurrentMember::class), new Reference(ReactionGuard::class), new Reference('twig'), new Reference('translator')], $container->getDefinition(ReactionController::class)->getArguments());
        self::assertEquals([new Reference(Reactions::class), new Reference(ReactionBoard::class), new Reference(CurrentMember::class), new Reference(ReactionGuard::class), new Reference(KitComponents::class), new Reference('request_stack'), new Reference('translator')], $container->getDefinition(ReactionsComponent::class)->getArguments());
        self::assertEquals([new Reference(ReactionRepository::class), new Reference(ReactionCatalog::class), new Reference(ReactionAccess::class), new Reference(CurrentMember::class), new Reference(VisibilityPolicy::class), new Reference(MemberNames::class), new Reference('translator'), new Reference(ReactionsPerMember::class)], $container->getDefinition(ReactionBoard::class)->getArguments());
        self::assertEquals([new Reference(ReactionBoard::class)], $container->getDefinition(ReactionsTwig::class)->getArguments());
        self::assertTrue($container->getDefinition(ReactionsTwig::class)->isAutoconfigured());
        self::assertSame(IntlExtension::class, $container->getDefinition(ReactionsServices::INTL_EXTENSION)->getClass());
        self::assertSame([[]], $container->getDefinition(ReactionsServices::INTL_EXTENSION)->getTag('twig.extension'));
        self::assertTrue($container->hasDefinition(NoMemberNames::class));
        self::assertTrue($container->hasDefinition(PlainEmojiImages::class));
        self::assertTrue($container->getDefinition(ReactionsComponent::class)->isAutowired());
        self::assertTrue($container->getDefinition(ReactionsComponent::class)->isAutoconfigured());
    }

    public function testTheDefaultListHasOnlyPositiveReactions(): void
    {
        $container = $this->load([]);
        $definition = $container->getDefinition('phil_reactions.source.unicode');

        self::assertSame(['unicode', 'source.unicode'], [$definition->getArgument(0), $definition->getArgument(1)]);
        /** @var list<array<string, mixed>> $reactions */
        $reactions = $definition->getArgument(2);
        self::assertSame(['like', 'love', 'laugh', 'celebrate', 'insightful', 'thanks'], array_column($reactions, 'code'));
        self::assertSame(['code' => 'like', 'emoji' => '👍', 'label' => 'reaction.like', 'private' => false, 'quick' => true, 'translation_domain' => 'phil_reactions'], $reactions[0]);
        self::assertSame(['site', 'source.site', []], $container->getDefinition('phil_reactions.source.site')->getArguments());
    }

    public function testTheListOfASite(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'unicode' => [['code' => 'yes', 'emoji' => 'x', 'label' => 'l'], ['code' => 'no', 'emoji' => 'y', 'label' => 'm', 'private' => true, 'quick' => false, 'translation_domain' => 'site']],
            'custom' => [['code' => 'gnome', 'image' => '/g', 'alt' => 'a', 'label' => 'g']],
            'order' => ['site', 'unicode'],
            'visibility' => ['panel' => 'members', 'counts' => 'members', 'names' => 'everyone'],
            'access' => 'a',
            'current_member' => 'b',
            'member_names' => 'c',
            'visibility_policy' => 'd',
            'rate_limiter' => 'limiter.mine',
            'kit_subdirectory' => 'ui',
            'per_member' => 3,
        ]);

        self::assertSame([3], $container->getDefinition(ReactionsPerMember::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryReactionRepository::class)->getTag('kernel.reset'));
        self::assertSame(InMemoryReactionRepository::class, (string) $container->getAlias(ReactionRepository::class));
        self::assertTrue($container->getAlias(ReactionRepository::class)->isPublic());
        self::assertFalse($container->hasDefinition(DoctrineReactionRepository::class));
        self::assertSame([
            ['code' => 'yes', 'emoji' => 'x', 'label' => 'l', 'private' => false, 'quick' => true, 'translation_domain' => 'phil_reactions'],
            ['code' => 'no', 'emoji' => 'y', 'label' => 'm', 'private' => true, 'quick' => false, 'translation_domain' => 'site'],
        ], $container->getDefinition('phil_reactions.source.unicode')->getArgument(2));
        self::assertSame([['code' => 'gnome', 'image' => '/g', 'alt' => 'a', 'label' => 'g', 'private' => false, 'quick' => false, 'translation_domain' => 'phil_reactions']], $container->getDefinition('phil_reactions.source.site')->getArgument(2));
        self::assertSame(['site', 'unicode'], $container->getDefinition(ReactionCatalog::class)->getArgument(2));
        self::assertSame(['members', 'members', 'everyone'], $container->getDefinition(ConfiguredVisibility::class)->getArguments());
        self::assertSame('a', (string) $container->getAlias(ReactionAccess::class));
        self::assertSame('b', (string) $container->getAlias(CurrentMember::class));
        self::assertSame('c', (string) $container->getAlias(MemberNames::class));
        self::assertSame('d', (string) $container->getAlias(VisibilityPolicy::class));
        self::assertFalse($container->hasDefinition(DenyingReactionAccess::class));
        self::assertFalse($container->hasDefinition(NoCurrentMember::class));
        self::assertEquals(new Reference('limiter.mine'), $container->getDefinition(SymfonyThrottle::class)->getArgument(0));
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());
    }

    public function testOneAudienceGivenLeavesTheOthersInPlace(): void
    {
        self::assertSame(['everyone', 'everyone', 'members'], $this->load(['visibility' => ['names' => 'members']])->getDefinition(ConfiguredVisibility::class)->getArguments());
    }

    public function testAnotherConnection(): void
    {
        self::assertEquals([new Reference('doctrine.dbal.mine_connection')], $this->load(['store' => 'doctrine', 'connection' => 'mine'])->getDefinition(DoctrineReactionRepository::class)->getArguments());
    }

    public function testAnAudienceThatDoesNotExist(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['visibility' => ['counts' => 'someone']]);
    }

    public function testTheCapOfOneIsAccepted(): void
    {
        self::assertSame([1], $this->load(['per_member' => 1])->getDefinition(ReactionsPerMember::class)->getArguments());
    }

    public function testTheCapIsDescribedWithItsLimit(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $tree = $configuration->getConfigTreeBuilder()->buildTree();
        self::assertInstanceOf(ArrayNode::class, $tree);
        $node = $tree->getChildren()['per_member'];
        self::assertInstanceOf(BaseNode::class, $node);

        self::assertSame('How many reactions a member gives one content: 1, which a new reaction replaces, or up to '.ReactionsPerMember::MAX.', each code once.', $node->getInfo());
    }

    public function testACapOutOfBounds(): void
    {
        foreach ([0, 21] as $cap) {
            try {
                $this->load(['per_member' => $cap]);
                self::fail('Accepted.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_reactions.per_member', $invalidConfigurationException->getMessage());
            }
        }

        self::assertSame([20], $this->load(['per_member' => 20])->getDefinition(ReactionsPerMember::class)->getArguments());
    }

    public function testSecurityWithoutAMemberServiceIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidConfigurationException('The option "phil_reactions.current_member" is required with Symfony Security: name the CurrentMember service that gives the stable identifier of a member, never an email address. "'.TokenCurrentMember::class.'" gives the user identifier of the security token, for an application whose user identifiers are stable and never contain "@".'));

        $this->load([], ['security']);
    }

    public function testWithSecurity(): void
    {
        $container = $this->load(['current_member' => TokenCurrentMember::class], ['security']);

        self::assertSame(AuthorizationCheckerAccess::class, (string) $container->getAlias(ReactionAccess::class));
        self::assertSame(TokenCurrentMember::class, (string) $container->getAlias(CurrentMember::class));
        self::assertEquals([new Reference('security.authorization_checker')], $container->getDefinition(AuthorizationCheckerAccess::class)->getArguments());
        self::assertEquals([new Reference('security.token_storage')], $container->getDefinition(TokenCurrentMember::class)->getArguments());
        self::assertFalse($container->hasDefinition(DenyingReactionAccess::class));
        self::assertFalse($container->hasDefinition(NoCurrentMember::class));
    }

    public function testApplicationPortsWinOverSecurity(): void
    {
        $container = $this->load(['access' => 'a', 'current_member' => 'b'], ['security']);

        self::assertSame('a', (string) $container->getAlias(ReactionAccess::class));
        self::assertFalse($container->hasDefinition(AuthorizationCheckerAccess::class));
        self::assertFalse($container->hasDefinition(TokenCurrentMember::class));
        self::assertFalse($container->hasDefinition(NoCurrentMember::class));
    }

    public function testTheMemberOfTheTokenNeedsSecurity(): void
    {
        $container = $this->load(['current_member' => TokenCurrentMember::class]);

        self::assertSame(TokenCurrentMember::class, (string) $container->getAlias(CurrentMember::class));
        self::assertFalse($container->hasDefinition(TokenCurrentMember::class));
    }

    public function testWithSettingsAndMedia(): void
    {
        $container = $this->load([], ['phil_settings', 'phil_media']);

        self::assertSame(SettingsVisibility::class, (string) $container->getAlias(VisibilityPolicy::class));
        self::assertSame(MediaEmojiImages::class, (string) $container->getAlias(EmojiImages::class));
        self::assertFalse($container->hasDefinition(PlainEmojiImages::class));
        self::assertEquals([new Reference(ConfiguredVisibility::class), new Reference(Settings::class)], $container->getDefinition(SettingsVisibility::class)->getArguments());
        self::assertEquals([new Reference(MediaRepository::class), new Reference(MediaExtension::class)], $container->getDefinition(MediaEmojiImages::class)->getArguments());
        self::assertSame('d', (string) $this->load(['visibility_policy' => 'd'], ['phil_settings'])->getAlias(VisibilityPolicy::class));
    }

    public function testTheEventsOnTheBus(): void
    {
        $definition = $this->load(['messenger' => true])->getDefinition(EventsOnTheBus::class);

        self::assertEquals([new Reference('messenger.default_bus')], $definition->getArguments());
        self::assertSame([['event' => ReactionAdded::class], ['event' => ReactionRemoved::class]], $definition->getTag('kernel.event_listener'));
    }

    public function testPrivacyWhenItIsThere(): void
    {
        $definition = $this->load([])->getDefinition(ReactionsPrivacy::class);

        self::assertEquals([new Reference(ReactionRepository::class)], $definition->getArguments());
        self::assertTrue($definition->hasTag('phil_privacy.exporter'));
        self::assertTrue($definition->hasTag('phil_privacy.eraser'));
    }

    public function testWhatIsPrepended(): void
    {
        $container = new ContainerBuilder();

        foreach (['framework', 'asset_mapper', 'doctrine', 'phil_settings'] as $alias) {
            $container->registerExtension($this->stub($alias));
        }

        $extension = $this->extension();
        $this->assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([[
            'csrf_protection' => ['stateless_token_ids' => ['phil_reactions']],
            'rate_limiter' => ['phil_reactions' => ['policy' => 'sliding_window', 'limit' => 30, 'interval' => '1 minute']],
        ]], $container->getExtensionConfig('framework'));
        self::assertSame([['paths' => [
            PhilReactionsBundle::CONTROLLERS_DIR => '@phil/reactions-bundle',
            PhilReactionsBundle::STYLES_DIR => 'phil-reactions',
        ]]], $container->getExtensionConfig('asset_mapper'));
        self::assertFileExists(PhilReactionsBundle::CONTROLLERS_DIR.'/reactions_controller.js');
        self::assertFileExists(PhilReactionsBundle::STYLES_DIR.'/reactions.css');
        self::assertSame(['orm' => ['mappings' => ['PhilReactionsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\ReactionsBundle\Domain\Model',
        ]]]], $container->getExtensionConfig('doctrine')[0]);

        /** @var array{settings: array<string, array<string, mixed>>} $settings */
        $settings = $container->getExtensionConfig('phil_settings')[0];
        self::assertSame(['reactions.panel', 'reactions.counts', 'reactions.names'], array_keys($settings['settings']));
        self::assertSame(['type' => 'choice', 'scope' => 'site', 'section' => 'general', 'default' => 'configured', 'choices' => ['configured', 'everyone', 'members', 'nobody'], 'translation_domain' => 'phil_reactions'], $settings['settings']['reactions.names']);
    }

    public function testNothingIsPrependedToWhatIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $this->assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([[], [], [], []], [$container->getExtensionConfig('framework'), $container->getExtensionConfig('asset_mapper'), $container->getExtensionConfig('doctrine'), $container->getExtensionConfig('phil_settings')]);
    }

    /**
     * TwigExtraBundle registers the extension of twig/intl-extra: the bundle leaves it to it.
     */
    public function testTheIntlExtensionOfTwigExtraBundleIsKept(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new TwigExtraExtension());
        $this->extension()->load([[]], $container);

        self::assertFalse($container->hasDefinition(ReactionsServices::INTL_EXTENSION));
    }

    /**
     * FrameworkBundle without AssetMapperBundle: nothing is written to `framework.asset_mapper`, an alias it would leave
     * undefined.
     */
    public function testNoAssetPathWithoutAssetMapperBundle(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->stub('framework'));

        $extension = $this->extension();
        $this->assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame(['csrf_protection', 'rate_limiter'], array_keys((array) $container->getExtensionConfig('framework')[0]));
        self::assertSame([], $container->getExtensionConfig('asset_mapper'));
    }

    public function testTheCsrfProtectionIsRequired(): void
    {
        $container = new ContainerBuilder();
        $pass = new CsrfProtectionPass();

        try {
            $pass->process($container);
            self::fail('Accepted.');
        } catch (LogicException $logicException) {
            self::assertStringContainsString('"phil_reactions"', $logicException->getMessage());
        }

        $container->register(CsrfProtectionPass::TOKEN_MANAGER);
        $pass->process($container);
        self::assertSame('security.csrf.token_manager', CsrfProtectionPass::TOKEN_MANAGER);
    }

    public function testTheSubdirectoryOfTheToolkit(): void
    {
        self::assertSame('', $this->kit(null, null));
        self::assertSame('ui', $this->kit(null, 'ui'));
        self::assertSame('own', $this->kit('own', 'ui'));
        self::assertSame('', $this->kit(null, false));
    }

    public function testTheBuildAddsThePasses(): void
    {
        $container = new ContainerBuilder();
        new PhilReactionsBundle()->build($container);

        $classes = array_map(get_class(...), $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        self::assertContains(CsrfProtectionPass::class, $classes);
        self::assertContains(KitSubdirectoryPass::class, $classes);
    }

    private function kit(?string $own, string|false|null $toolkit): mixed
    {
        $container = new ContainerBuilder();
        $container->register(KitComponents::class)->setArguments([$own]);

        if (null !== $toolkit) {
            $container->setParameter('.ux_toolkit.components.subdirectory', false === $toolkit ? null : $toolkit);
        }

        new KitSubdirectoryPass()->process($container);

        return $container->getDefinition(KitComponents::class)->getArgument(0);
    }

    /**
     * @param array<string, mixed> $config
     * @param list<string>         $extensions
     */
    private function load(array $config, array $extensions = []): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ($extensions as $alias) {
            $container->registerExtension($this->stub($alias));
        }

        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilReactionsBundle()->getContainerExtension();
        $this->assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function stub(string $alias): ExtensionInterface
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            #[Override]
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            #[Override]
            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
