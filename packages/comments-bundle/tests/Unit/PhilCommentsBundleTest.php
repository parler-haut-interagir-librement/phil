<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\CommentsBundle\Application\CommentPolicy;
use Phil\CommentsBundle\Application\Comments;
use Phil\CommentsBundle\Application\DefaultCommentPolicy;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Infrastructure\DependencyInjection\CommentsServices;
use Phil\CommentsBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\CommentsBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineCommentRepository;
use Phil\CommentsBundle\Infrastructure\Doctrine\DoctrineThreadRepository;
use Phil\CommentsBundle\Infrastructure\Http\ClientConfig;
use Phil\CommentsBundle\Infrastructure\Http\CommentsController;
use Phil\CommentsBundle\Infrastructure\Http\CommentsRequest;
use Phil\CommentsBundle\Infrastructure\Http\DiscussionJson;
use Phil\CommentsBundle\Infrastructure\Http\PageController;
use Phil\CommentsBundle\Infrastructure\Http\PageForm;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryThreadRepository;
use Phil\CommentsBundle\Infrastructure\Twig\Components\PageCommentsComponent;
use Phil\CommentsBundle\Infrastructure\Twig\KitComponents;
use Phil\CommentsBundle\PhilCommentsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCommentsBundle::class)]
#[CoversClass(CommentsServices::class)]
final class PhilCommentsBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_comments', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineThreadRepository::class, DoctrineCommentRepository::class, DefaultCommentPolicy::class, Comments::class, DiscussionJson::class, CommentsRequest::class, CommentsController::class, ClientConfig::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineThreadRepository::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineCommentRepository::class)->getArguments());
        self::assertSame([DoctrineThreadRepository::class, true], [(string) $container->getAlias(ThreadRepository::class), $container->getAlias(ThreadRepository::class)->isPublic()]);
        self::assertSame([DoctrineCommentRepository::class, true], [(string) $container->getAlias(CommentRepository::class), $container->getAlias(CommentRepository::class)->isPublic()]);
        self::assertSame(DefaultCommentPolicy::class, (string) $container->getAlias(CommentPolicy::class));
        self::assertEquals([new Reference(ThreadRepository::class), new Reference(CommentRepository::class), new Reference('clock'), new Reference(CommentPolicy::class), null, new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(Comments::class)->getArguments());
        self::assertEquals([new Reference(CommentPolicy::class), null], $container->getDefinition(DiscussionJson::class)->getArguments());
        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(CommentsRequest::class)->getArguments());
        self::assertEquals([new Reference(Comments::class), new Reference(DiscussionJson::class), new Reference(CommentsRequest::class), null, false], $container->getDefinition(CommentsController::class)->getArguments());
        self::assertTrue($container->getDefinition(CommentsController::class)->hasTag('controller.service_arguments'));
        self::assertEquals([new Reference('router', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(CommentsRequest::class), null, false], $container->getDefinition(ClientConfig::class)->getArguments());

        foreach ([Comments::class, CommentsController::class, ClientConfig::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        self::assertFalse($container->getDefinition(DiscussionJson::class)->isPublic());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['store' => 'memory', 'author_provider' => 'app.authors', 'policy' => 'app.policy', 'author_directory' => 'app.names']);

        self::assertSame(['service_container', InMemoryThreadRepository::class, InMemoryCommentRepository::class, Comments::class, DiscussionJson::class, CommentsRequest::class, CommentsController::class, ClientConfig::class], array_keys($container->getDefinitions()));
        self::assertSame([InMemoryThreadRepository::class, true], [(string) $container->getAlias(ThreadRepository::class), $container->getAlias(ThreadRepository::class)->isPublic()]);
        self::assertSame([InMemoryCommentRepository::class, true], [(string) $container->getAlias(CommentRepository::class), $container->getAlias(CommentRepository::class)->isPublic()]);
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryThreadRepository::class)->getTag('kernel.reset'));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryCommentRepository::class)->getTag('kernel.reset'));
        self::assertSame('app.policy', (string) $container->getAlias(CommentPolicy::class));
        self::assertEquals([new Reference(CommentPolicy::class), new Reference('app.names')], $container->getDefinition(DiscussionJson::class)->getArguments());
        self::assertEquals([new Reference('app.authors'), true], \array_slice($container->getDefinition(CommentsController::class)->getArguments(), 3));
        self::assertFalse($this->load(['author_provider' => 'app.authors'])->getDefinition(CommentsController::class)->getArgument(4));
        self::assertFalse($this->load(['policy' => 'app.policy'])->getDefinition(CommentsController::class)->getArgument(4));
        self::assertEquals([new Reference('doctrine.orm.reviews_entity_manager')], $this->load(['store' => 'doctrine', 'entity_manager' => 'reviews'])->getDefinition(DoctrineThreadRepository::class)->getArguments());
    }

    public function testTheCommentsOnThePageNeedTwigComponents(): void
    {
        self::assertFalse($this->load([])->hasDefinition(PageCommentsComponent::class));

        $container = $this->withTwigComponents();
        $this->extension()->load([['author_provider' => 'app.authors', 'policy' => 'app.policy', 'aria_roles' => true, 'kit_subdirectory' => 'ui/kit']], $container);

        self::assertTrue($container->getDefinition(ClientConfig::class)->getArgument(3));
        self::assertSame(['ui/kit'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertEquals([new Reference('security.csrf.token_manager'), new Reference('uri_signer'), new Reference('router')], $container->getDefinition(PageForm::class)->getArguments());
        self::assertEquals([new Reference(Comments::class), new Reference(PageForm::class), new Reference('twig'), new Reference('translator'), new Reference('app.authors'), true], $container->getDefinition(PageController::class)->getArguments());
        self::assertTrue($container->getDefinition(PageController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(PageController::class)->isPublic());
        self::assertEquals([new Reference(Comments::class), new Reference(CommentPolicy::class), new Reference(DiscussionJson::class), new Reference(PageForm::class), new Reference(ClientConfig::class), new Reference(KitComponents::class), new Reference('request_stack'), new Reference('app.authors')], $container->getDefinition(PageCommentsComponent::class)->getArguments());
        self::assertTrue($container->getDefinition(PageCommentsComponent::class)->isAutoconfigured());

        $partial = $this->withTwigComponents();
        $this->extension()->load([['author_provider' => 'app.authors']], $partial);
        self::assertSame([false, null], [$partial->getDefinition(PageController::class)->getArgument(5), $partial->getDefinition(KitComponents::class)->getArgument(0)]);
        self::assertFalse($partial->getDefinition(ClientConfig::class)->getArgument(3));

        $nobody = $this->withTwigComponents();
        $this->extension()->load([['policy' => 'app.policy']], $nobody);
        self::assertSame([null, false], [$nobody->getDefinition(PageController::class)->getArgument(4), $nobody->getDefinition(PageController::class)->getArgument(5)]);
        self::assertNull($nobody->getDefinition(PageCommentsComponent::class)->getArgument(7));
    }

    public function testThePassesAreAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilCommentsBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getPasses());

        self::assertContains(CsrfProtectionPass::class, $passes);
        self::assertContains(KitSubdirectoryPass::class, $passes);
    }

    public function testTheMemberSearchIsNamedWhenTheMentionsBundleIsEnabled(): void
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.bundles' => ['PhilMentionsBundle' => 'App']]));
        $this->extension()->load([[]], $container);
        self::assertSame('phil_block_editor_mentions', $container->getDefinition(ClientConfig::class)->getArgument(2));

        $other = new ContainerBuilder(new ParameterBag(['kernel.bundles' => ['OtherBundle' => 'App']]));
        $this->extension()->load([[]], $other);
        self::assertNull($other->getDefinition(ClientConfig::class)->getArgument(2));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([['store' => 'redis'], ['entity_manager' => ''], ['aria_roles' => 'yes']] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_comments', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheMappingAndTheAssetsArePrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension(new FrameworkExtension());

        $assetMapper = new AssetMapperBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $assetMapper);
        $container->registerExtension($assetMapper);
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommentsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\CommentsBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_comments']]]], $container->getExtensionConfig('framework'));
        self::assertSame([['paths' => [
            PhilCommentsBundle::CONTROLLERS_DIR => '@phil/comments-bundle',
            PhilCommentsBundle::STYLES_DIR => 'phil-comments',
        ]]], $container->getExtensionConfig('asset_mapper'));
        self::assertFileExists(PhilCommentsBundle::CONTROLLERS_DIR.'/comments_controller.js');
        self::assertFileExists(PhilCommentsBundle::STYLES_DIR.'/comments.css');

        // FrameworkBundle without AssetMapperBundle: nothing is written to the alias it would leave undefined.
        $withoutAssets = new ContainerBuilder();
        $withoutAssets->registerExtension(new FrameworkExtension());
        $withoutAssets->registerExtension($extension);

        $extension->prepend($withoutAssets);
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_comments']]]], $withoutAssets->getExtensionConfig('framework'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([[], [], []], [$bare->getExtensionConfig('doctrine'), $bare->getExtensionConfig('framework'), $bare->getExtensionConfig('phil_comments')]);
    }

    private function withTwigComponents(): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag(['kernel.bundles' => ['TwigComponentBundle' => 'App']]));
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
        $extension = new PhilCommentsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
