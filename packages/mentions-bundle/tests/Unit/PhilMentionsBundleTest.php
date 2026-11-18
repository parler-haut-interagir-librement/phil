<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\BlockEditorBundle\PhilBlockEditorBundle;
use Phil\CommentsBundle\Application\Event\CommentDeleted;
use Phil\CommentsBundle\Application\Event\CommentWritten;
use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Event\MemberMentioned;
use Phil\MentionsBundle\Application\Extraction\TextMentions;
use Phil\MentionsBundle\Application\MentionLimits;
use Phil\MentionsBundle\Application\MentionRecorder;
use Phil\MentionsBundle\Application\Mentions;
use Phil\MentionsBundle\Application\Rendering\MentionLinker;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use Phil\MentionsBundle\Infrastructure\BlockEditor\MemberReferenceSearch;
use Phil\MentionsBundle\Infrastructure\Comments\CommentMentions;
use Phil\MentionsBundle\Infrastructure\Comments\MemberAuthorDirectory;
use Phil\MentionsBundle\Infrastructure\Comments\RecordCommentMentions;
use Phil\MentionsBundle\Infrastructure\DependencyInjection\MentionsPass;
use Phil\MentionsBundle\Infrastructure\DependencyInjection\MentionsServices;
use Phil\MentionsBundle\Infrastructure\Doctrine\BinaryKeysListener;
use Phil\MentionsBundle\Infrastructure\Doctrine\DoctrineMentionRepository;
use Phil\MentionsBundle\Infrastructure\Memory\InMemoryMentionRepository;
use Phil\MentionsBundle\Infrastructure\Notifications\NotifyMentionedMember;
use Phil\MentionsBundle\Infrastructure\Twig\MentionsExtension;
use Phil\MentionsBundle\PhilMentionsBundle;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\SettingsBundle\PhilSettingsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilMentionsBundle::class)]
#[CoversClass(MentionsServices::class)]
final class PhilMentionsBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load(['member_directory' => 'app.members']);

        self::assertSame('phil_mentions', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineMentionRepository::class, BinaryKeysListener::class, MentionPattern::class, MentionLimits::class, TextMentions::class, MentionRecorder::class, Mentions::class, MentionLinker::class, NotifyMentionedMember::class, MentionsExtension::class, MemberReferenceSearch::class, MemberAuthorDirectory::class, CommentMentions::class, RecordCommentMentions::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineMentionRepository::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'loadClassMetadata']]], $container->getDefinition(BinaryKeysListener::class)->getTags());
        self::assertSame([DoctrineMentionRepository::class, true], [(string) $container->getAlias(MentionRepository::class), $container->getAlias(MentionRepository::class)->isPublic()]);
        self::assertSame('app.members', (string) $container->getAlias(MemberDirectory::class));
        self::assertSame([[MentionPattern::class, 'of'], [MentionPattern::DEFAULT]], [$container->getDefinition(MentionPattern::class)->getFactory(), $container->getDefinition(MentionPattern::class)->getArguments()]);
        self::assertSame([20, 100_000], $container->getDefinition(MentionLimits::class)->getArguments());
        self::assertEquals([new Reference(MentionPattern::class)], $container->getDefinition(TextMentions::class)->getArguments());
        self::assertEquals([new Reference(MentionRepository::class), new Reference(MemberDirectory::class), new Reference('clock'), new Reference(MentionLimits::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(MentionRecorder::class)->getArguments());
        self::assertEquals([new Reference(MentionRecorder::class), new Reference(TextMentions::class), new Reference(MentionLimits::class)], $container->getDefinition(Mentions::class)->getArguments());
        self::assertEquals([new Reference(MemberDirectory::class), new Reference(MentionPattern::class), 20], $container->getDefinition(MentionLinker::class)->getArguments());
        self::assertEquals([new Reference(MentionsPass::NOTIFICATIONS), new Reference(MentionsPass::PREFERENCES), 'member.mentioned'], $container->getDefinition(NotifyMentionedMember::class)->getArguments());
        self::assertSame([['event' => MemberMentioned::class]], $container->getDefinition(NotifyMentionedMember::class)->getTag('kernel.event_listener'));
        self::assertNull($container->getParameter(MentionsPass::PREFERENCES_PARAMETER));
        self::assertEquals([new Reference(MentionLinker::class)], $container->getDefinition(MentionsExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(MentionsExtension::class)->isAutoconfigured());
        self::assertEquals([new Reference(MemberDirectory::class)], $container->getDefinition(MemberReferenceSearch::class)->getArguments());
        self::assertEquals([new Reference(MemberDirectory::class)], $container->getDefinition(MemberAuthorDirectory::class)->getArguments());
        self::assertEquals([new Reference(Mentions::class), false], $container->getDefinition(CommentMentions::class)->getArguments());
        self::assertEquals([new Reference(CommentMentions::class)], $container->getDefinition(RecordCommentMentions::class)->getArguments());
        self::assertSame([['event' => CommentWritten::class, 'method' => 'written'], ['event' => CommentDeleted::class, 'method' => 'deleted']], $container->getDefinition(RecordCommentMentions::class)->getTag('kernel.event_listener'));

        foreach ([MentionRecorder::class, Mentions::class, MentionLinker::class, CommentMentions::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        foreach ([MentionPattern::class, TextMentions::class, NotifyMentionedMember::class, MemberReferenceSearch::class, MemberAuthorDirectory::class, RecordCommentMentions::class] as $private) {
            self::assertFalse($container->getDefinition($private)->isPublic(), $private);
        }
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'store' => 'memory',
            'member_directory' => 'app.members',
            'pattern' => '[a-z]{3,20}',
            'limits' => ['per_text' => 5, 'text_length' => 1000],
            'notification_type' => 'mention',
            'preferences' => 'app.preferences',
            'comments' => ['markdown' => true, 'record' => false],
        ]);

        self::assertSame([InMemoryMentionRepository::class, true], [(string) $container->getAlias(MentionRepository::class), $container->getAlias(MentionRepository::class)->isPublic()]);
        self::assertFalse($container->hasDefinition(DoctrineMentionRepository::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryMentionRepository::class)->getTag('kernel.reset'));
        self::assertSame(['[a-z]{3,20}'], $container->getDefinition(MentionPattern::class)->getArguments());
        self::assertSame([5, 1000], $container->getDefinition(MentionLimits::class)->getArguments());
        self::assertSame(5, $container->getDefinition(MentionLinker::class)->getArgument(2));
        self::assertSame('mention', $container->getDefinition(NotifyMentionedMember::class)->getArgument(2));
        self::assertSame('app.preferences', $container->getParameter(MentionsPass::PREFERENCES_PARAMETER));
        self::assertTrue($container->getDefinition(CommentMentions::class)->getArgument(1));
        self::assertFalse($container->hasDefinition(RecordCommentMentions::class));
        self::assertEquals([new Reference('doctrine.orm.mentions_entity_manager')], $this->load(['member_directory' => 'm', 'store' => 'doctrine', 'entity_manager' => 'mentions'])->getDefinition(DoctrineMentionRepository::class)->getArguments());
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([[], ['member_directory' => ''], ['member_directory' => 'm', 'store' => 'redis'], ['member_directory' => 'm', 'entity_manager' => ''], ['member_directory' => 'm', 'pattern' => 'a*'], ['member_directory' => 'm', 'pattern' => ['a']], ['member_directory' => 'm', 'limits' => ['per_text' => 0]], ['member_directory' => 'm', 'limits' => ['per_text' => 1001]], ['member_directory' => 'm', 'limits' => ['text_length' => 0]], ['member_directory' => 'm', 'limits' => ['text_length' => 10_000_001]], ['member_directory' => 'm', 'notification_type' => '']] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_mentions', $invalidConfigurationException->getMessage());
            }
        }

        self::assertSame([1000, 10_000_000], $this->load(['member_directory' => 'm', 'limits' => ['per_text' => 1000, 'text_length' => 10_000_000]])->getDefinition(MentionLimits::class)->getArguments());
        self::assertSame([1, 1], $this->load(['member_directory' => 'm', 'limits' => ['per_text' => 1, 'text_length' => 1]])->getDefinition(MentionLimits::class)->getArguments());
    }

    public function testThePassIsAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilMentionsBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof MentionsPass));
    }

    public function testTheOtherBundlesAreConfiguredWhenEnabled(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension($this->of(new AssetMapperBundle()));
        $container->registerExtension($this->of(new PhilNotificationsBundle()));
        $container->registerExtension($this->of(new PhilSettingsBundle()));
        $container->registerExtension($this->of(new PhilBlockEditorBundle()));
        $container->registerExtension($extension);
        $container->prependExtensionConfig('phil_mentions', ['notification_type' => 'first']);
        $container->prependExtensionConfig('phil_mentions', ['member_directory' => 'm', 'notification_type' => 'last']);

        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilMentionsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\MentionsBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
        self::assertSame([['types' => ['first' => ['channels' => ['site', 'email']]]]], $container->getExtensionConfig('phil_notifications'));
        self::assertSame([['settings' => ['notifications.mentions' => [
            'type' => 'boolean',
            'default' => true,
            'section' => 'notifications',
            'translation_domain' => 'phil_mentions',
            'after' => ['notifications.web_push'],
        ]]]], $container->getExtensionConfig('phil_settings'));
        self::assertSame([['mention_search' => MemberReferenceSearch::class]], $container->getExtensionConfig('phil_block_editor'));
        self::assertSame([['paths' => [
            PhilMentionsBundle::CONTROLLERS_DIR => '@phil/mentions-bundle',
            PhilMentionsBundle::STYLES_DIR => 'phil-mentions',
        ]]], $container->getExtensionConfig('asset_mapper'));
        self::assertSame([], $container->getExtensionConfig('framework'));
        self::assertFileExists(PhilMentionsBundle::CONTROLLERS_DIR.'/mention_controller.js');
        self::assertFileExists(PhilMentionsBundle::STYLES_DIR.'/mentions.css');

        $alone = new ContainerBuilder();
        $alone->registerExtension(new FrameworkExtension());
        $alone->registerExtension($this->of(new PhilNotificationsBundle()));
        $alone->registerExtension($extension);
        $alone->prependExtensionConfig('phil_mentions', ['member_directory' => 'm', 'notification_type' => ['not', 'a', 'string']]);

        $extension->prepend($alone);
        self::assertSame([['types' => ['member.mentioned' => ['channels' => ['site', 'email']]]]], $alone->getExtensionConfig('phil_notifications'));
        // FrameworkBundle without AssetMapperBundle: nothing is written to the alias it would leave undefined.
        self::assertSame([], $alone->getExtensionConfig('framework'));

        foreach ([[true, true], [null, true], [[], true], [['subject' => 'mailto:a@b.c'], true], [['enabled' => true], true], [false, false], [['enabled' => false], false], ['yes', false]] as [$option, $pushed]) {
            $withPush = new ContainerBuilder();
            $withPush->registerExtension($this->of(new PhilNotificationsBundle()));
            $withPush->registerExtension($extension);
            $withPush->prependExtensionConfig('phil_mentions', ['member_directory' => 'm']);
            $withPush->loadFromExtension('phil_notifications', ['types' => []]);
            $withPush->loadFromExtension('phil_notifications', ['web_push' => $option]);

            $extension->prepend($withPush);
            self::assertSame(
                ['types' => ['member.mentioned' => ['channels' => $pushed ? ['site', 'email', 'web_push'] : ['site', 'email']]]],
                array_last($withPush->getExtensionConfig('phil_notifications')),
                (string) json_encode($option),
            );
        }

        $overridden = new ContainerBuilder();
        $overridden->registerExtension($this->of(new PhilNotificationsBundle()));
        $overridden->registerExtension($extension);
        $overridden->prependExtensionConfig('phil_mentions', ['member_directory' => 'm']);
        $overridden->loadFromExtension('phil_notifications', ['web_push' => true]);
        $overridden->loadFromExtension('phil_notifications', ['web_push' => ['enabled' => false]]);

        $extension->prepend($overridden);
        self::assertSame(['types' => ['member.mentioned' => ['channels' => ['site', 'email']]]], array_last($overridden->getExtensionConfig('phil_notifications')));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([], $bare->getExtensionConfig('doctrine'));
        self::assertSame([], $bare->getExtensionConfig('framework'));
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

    private function extension(): ExtensionInterface
    {
        return $this->of(new PhilMentionsBundle());
    }

    private function of(object $bundle): ExtensionInterface
    {
        self::assertTrue(method_exists($bundle, 'getContainerExtension'));
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
