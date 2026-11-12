<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit;

use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SocialCards\Card\CardGenerator;
use Phil\SocialCards\Card\DirectoryCardStore;
use Phil\SocialCards\Card\GdCardRenderer;
use Phil\SocialCards\Contract\CardStore;
use Phil\SocialCards\Preview\TagReader;
use Phil\SocialCardsBundle\Application\CardTemplates;
use Phil\SocialCardsBundle\Application\Contract\CardRequestStore;
use Phil\SocialCardsBundle\Application\Contract\PageFetcher;
use Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Phil\SocialCardsBundle\Application\SharePage;
use Phil\SocialCardsBundle\Application\SocialCards;
use Phil\SocialCardsBundle\Infrastructure\Console\CheckSocialTagsCommand;
use Phil\SocialCardsBundle\Infrastructure\DependencyInjection\RequiredServicesPass;
use Phil\SocialCardsBundle\Infrastructure\DependencyInjection\SocialCardsServices;
use Phil\SocialCardsBundle\Infrastructure\Http\GuardedPageFetcher;
use Phil\SocialCardsBundle\Infrastructure\Http\LinkPreviewController;
use Phil\SocialCardsBundle\Infrastructure\Http\ShareMetadataFactory;
use Phil\SocialCardsBundle\Infrastructure\Http\SocialCardController;
use Phil\SocialCardsBundle\Infrastructure\Media\MediaShareImages;
use Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCardHandler;
use Phil\SocialCardsBundle\Infrastructure\Messenger\MessengerCardQueue;
use Phil\SocialCardsBundle\Infrastructure\Store\DirectoryCardRequestStore;
use Phil\SocialCardsBundle\Infrastructure\Twig\Components\LinkPreviewComponent;
use Phil\SocialCardsBundle\Infrastructure\Twig\SocialMetaExtension;
use Phil\SocialCardsBundle\PhilSocialCardsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilSocialCardsBundle::class)]
#[CoversClass(SocialCardsServices::class)]
#[CoversClass(RequiredServicesPass::class)]
final class PhilSocialCardsBundleTest extends TestCase
{
    private const array TEMPLATE = ['title_font' => '/fonts/title.ttf'];

    public function testTheDefaultsDrawNoCardWithoutATemplate(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_social_cards', $this->extension()->getAlias());
        self::assertSame(['service_container', SharePage::class, ShareMetadataFactory::class, SocialMetaExtension::class, SocialCardsServices::HTTP_CLIENT, GuardedPageFetcher::class, TagReader::class, LinkPreviews::class, LinkPreviewController::class, LinkPreviewComponent::class, CheckSocialTagsCommand::class], array_values(array_diff(array_keys($container->getDefinitions()), [MediaShareImages::class])));
        self::assertTrue($container->hasDefinition(MediaShareImages::class), 'phil/media-bundle is installed beside the bundle.');
        self::assertEquals([new Reference(MediaExtension::class)], $container->getDefinition(MediaShareImages::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(SharePage::class)->getTag('kernel.reset'));
        self::assertTrue($container->getDefinition(SharePage::class)->isPublic());
        self::assertEquals([
            new Reference(SharePage::class),
            new Reference('request_stack'),
            new Reference('router'),
            [],
            new TaggedIteratorArgument(SocialCardsServices::SOURCE_TAG),
            null,
            new Reference(MediaShareImages::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $container->getDefinition(ShareMetadataFactory::class)->getArguments());
        self::assertTrue($container->getDefinition(ShareMetadataFactory::class)->isPublic());
        self::assertEquals([new Reference(ShareMetadataFactory::class), new Reference(SharePage::class)], $container->getDefinition(SocialMetaExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(SocialMetaExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(SocialMetaExtension::class)->hasTag('twig.runtime'));
        self::assertEquals([new Reference(SocialCardsServices::HTTP_CLIENT)], $container->getDefinition(GuardedPageFetcher::class)->getArguments());
        $limits = $container->getDefinition(SocialCardsServices::HTTP_CLIENT)->getArgument(1);
        self::assertInstanceOf(Definition::class, $limits);
        self::assertSame([5.0, 10.0, 1_048_576, 3, 'PhilSocialCards/0.1 (link preview)'], $limits->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(GuardedPageFetcher::class)->getTag('kernel.reset'));
        self::assertSame(GuardedPageFetcher::class, (string) $container->getAlias(PageFetcher::class));
        self::assertSame([1_048_576], $container->getDefinition(TagReader::class)->getArguments());
        self::assertEquals([new Reference(PageFetcher::class), new Reference(TagReader::class), new Reference('cache.app', ContainerInterface::NULL_ON_INVALID_REFERENCE), 86_400], $container->getDefinition(LinkPreviews::class)->getArguments());
        self::assertTrue($container->getDefinition(LinkPreviews::class)->isPublic());
        self::assertEquals([new Reference(LinkPreviews::class)], $container->getDefinition(LinkPreviewComponent::class)->getArguments());
        self::assertTrue($container->getDefinition(LinkPreviewComponent::class)->isAutoconfigured());
        self::assertEquals([new Reference('http_kernel'), new Reference('router.request_context'), new Reference(LinkPreviews::class), new Reference(TagReader::class)], $container->getDefinition(CheckSocialTagsCommand::class)->getArguments());
        self::assertSame([['command' => 'phil:social-cards:check', 'description' => 'Checks the Open Graph, X and fediverse tags of a page.']], $container->getDefinition(CheckSocialTagsCommand::class)->getTag('console.command'));
        self::assertSame([[]], $container->getAutoconfiguredInstanceof()[ShareMetadataSource::class]->getTag(SocialCardsServices::SOURCE_TAG));
        self::assertSame('phil_social_cards.source', SocialCardsServices::SOURCE_TAG);
    }

    public function testCardsWithTheirTemplates(): void
    {
        $container = $this->load([
            'site_name' => 'The site',
            'locale' => 'fr-ca',
            'twitter_site' => '@site',
            'fediverse_creator' => '@me@example.social',
            'default_image' => ['url' => 'https://example.com/default.png', 'alt' => 'Default'],
            'cards' => ['templates' => ['default' => self::TEMPLATE, 'dark' => [...self::TEMPLATE, 'background' => '#000', 'padding' => 40]], 'directory' => '/var/cards', 'cache_max_age' => 600],
            'preview' => ['enabled' => false],
        ]);

        self::assertEquals([['default' => self::TEMPLATE, 'dark' => [...self::TEMPLATE, 'background' => '#000', 'padding' => 40]], 'default'], $container->getDefinition(CardTemplates::class)->getArguments());
        self::assertSame(['/var/cards'], $container->getDefinition(DirectoryCardStore::class)->getArguments());
        self::assertSame(DirectoryCardStore::class, (string) $container->getAlias(CardStore::class));
        self::assertSame(['/var/cards'], $container->getDefinition(DirectoryCardRequestStore::class)->getArguments());
        self::assertSame(DirectoryCardRequestStore::class, (string) $container->getAlias(CardRequestStore::class));
        self::assertTrue($container->hasDefinition(GdCardRenderer::class));
        self::assertEquals([new Reference(GdCardRenderer::class), new Reference(CardStore::class)], $container->getDefinition(CardGenerator::class)->getArguments());
        self::assertEquals([new Reference('messenger.default_bus')], $container->getDefinition(MessengerCardQueue::class)->getArguments());
        self::assertEquals([new Reference(SocialCards::class)], $container->getDefinition(GenerateSocialCardHandler::class)->getArguments());
        self::assertTrue($container->getDefinition(GenerateSocialCardHandler::class)->hasTag('messenger.message_handler'));
        self::assertEquals([new Reference(CardTemplates::class), new Reference(CardGenerator::class), new Reference(CardRequestStore::class), new Reference(CardStore::class), new Reference(MessengerCardQueue::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(SocialCards::class)->getArguments());
        self::assertTrue($container->getDefinition(SocialCards::class)->isPublic());
        self::assertEquals([new Reference(SocialCards::class), 600, new Reference(ResponseTags::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(SocialCardController::class)->getArguments());
        self::assertTrue($container->getDefinition(SocialCardController::class)->isPublic());
        self::assertTrue($container->getDefinition(SocialCardController::class)->hasTag('controller.service_arguments'));
        self::assertEquals(['site_name' => 'The site', 'locale' => 'fr-ca', 'twitter_site' => '@site', 'fediverse_creator' => '@me@example.social', 'image' => ['url' => 'https://example.com/default.png', 'alt' => 'Default']], $container->getDefinition(ShareMetadataFactory::class)->getArgument(3));
        self::assertEquals(new Reference(SocialCards::class), $container->getDefinition(ShareMetadataFactory::class)->getArgument(5));
        self::assertFalse($container->hasDefinition(LinkPreviews::class));
        self::assertFalse($container->hasDefinition(CheckSocialTagsCommand::class));

        self::assertFalse($this->load(['cards' => ['templates' => ['default' => self::TEMPLATE], 'async' => false]])->hasDefinition(MessengerCardQueue::class));
        self::assertSame(0, $this->load(['cards' => ['templates' => ['default' => self::TEMPLATE], 'cache_max_age' => 0]])->getDefinition(SocialCardController::class)->getArgument(1));
        self::assertFalse($this->load(['cards' => ['templates' => ['default' => self::TEMPLATE], 'enabled' => false]])->hasDefinition(SocialCards::class));
        self::assertSame('dark', $this->load(['cards' => ['templates' => ['dark' => self::TEMPLATE], 'default_template' => 'dark']])->getDefinition(CardTemplates::class)->getArgument(1));
        self::assertSame('%kernel.project_dir%/var/social-cards', $this->load(['cards' => ['templates' => ['default' => self::TEMPLATE]]])->getDefinition(DirectoryCardStore::class)->getArgument(0));
        self::assertSame(31_536_000, $this->load(['cards' => ['templates' => ['default' => self::TEMPLATE]]])->getDefinition(SocialCardController::class)->getArgument(1));
        $defaults = $this->load(['default_image' => 'https://example.com/i.png'])->getDefinition(ShareMetadataFactory::class)->getArgument(3);
        self::assertSame(['image' => 'https://example.com/i.png'], $defaults);
    }

    public function testThePreviewOptions(): void
    {
        $container = $this->load(['preview' => ['timeout' => 0.1, 'max_duration' => 120.0, 'max_bytes' => 1024, 'max_redirects' => 0, 'cache_ttl' => 0, 'user_agent' => 'Bot/1']]);

        $client = $container->getDefinition(SocialCardsServices::HTTP_CLIENT);
        $limits = $client->getArgument(1);
        self::assertInstanceOf(Definition::class, $limits);
        self::assertSame([SafeHttpClient::class, Limits::class, [0.1, 120.0, 1024, 0, 'Bot/1']], [$client->getClass(), $limits->getClass(), $limits->getArguments()]);
        self::assertEquals(new Reference('http_client', ContainerInterface::NULL_ON_INVALID_REFERENCE), $client->getArgument(0));
        self::assertSame([['method' => 'reset']], $client->getTag('kernel.reset'));
        self::assertEquals([new Reference(SocialCardsServices::HTTP_CLIENT)], $container->getDefinition(GuardedPageFetcher::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(GuardedPageFetcher::class)->getTag('kernel.reset'));
        self::assertSame(0, $container->getDefinition(LinkPreviews::class)->getArgument(3));
        $upper = $this->load(['preview' => ['timeout' => 60.0, 'max_duration' => 0.1, 'max_bytes' => 16_777_216, 'max_redirects' => 10]])->getDefinition(SocialCardsServices::HTTP_CLIENT)->getArgument(1);
        self::assertInstanceOf(Definition::class, $upper);
        self::assertSame([60.0, 0.1, 16_777_216, 10, 'PhilSocialCards/0.1 (link preview)'], $upper->getArguments());

        $controller = $container->getDefinition(LinkPreviewController::class);
        self::assertEquals([new Reference(LinkPreviews::class), null], $controller->getArguments());
        self::assertTrue($controller->isPublic());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertEquals(new Reference('limiter.previews'), $this->load(['preview' => ['limiter' => 'limiter.previews']])->getDefinition(LinkPreviewController::class)->getArgument(1));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'a locale' => [['locale' => 'french'], '"french" is not a locale, as fr_CA.'];
        yield 'an account on X' => [['twitter_site' => 'a.b'], '"a.b" is not an account on X.'];
        yield 'an account on the fediverse' => [['fediverse_creator' => '@nobody'], '"@nobody" is not an account on the fediverse.'];
        yield 'an image' => [['default_image' => '/relative.png'], 'is not an image: an absolute address, or url, alt, width, height and type.'];
        yield 'a missing default template' => [['cards' => ['templates' => ['dark' => self::TEMPLATE]]], 'The default card template is not among the declared templates'];
        yield 'a template without a font' => [['cards' => ['templates' => ['default' => ['background' => '#fff']]]], 'is not a card template: an array of title_font, subtitle_font, background, title_color, subtitle_color, accent, background_image, logo, padding, title_max_size, title_min_size, title_max_lines, subtitle_size, subtitle_max_lines, with title_font, sizes as integers and colours as #rrggbb.'];
        yield 'a template with a bad background' => [['cards' => ['templates' => ['default' => [...self::TEMPLATE, 'background' => 'nope']]]], 'is not a card template'];
        yield 'a template with a padding that is no integer' => [['cards' => ['templates' => ['default' => [...self::TEMPLATE, 'padding' => '80']]]], 'is not a card template'];
        yield 'a template that is no array' => [['cards' => ['templates' => ['default' => 'title.ttf']]], 'is not a card template'];
        yield 'a template with an unknown key' => [['cards' => ['templates' => ['default' => [...self::TEMPLATE, 'colour' => '#fff']]]], 'is not a card template'];
        yield 'a template with a bad colour' => [['cards' => ['templates' => ['default' => [...self::TEMPLATE, 'accent' => 'red']]]], 'is not a card template'];
        yield 'a template with a colour that is no string' => [['cards' => ['templates' => ['default' => [...self::TEMPLATE, 'title_color' => 1]]]], 'is not a card template'];
        yield 'a template with a size that is no integer' => [['cards' => ['templates' => ['default' => [...self::TEMPLATE, 'subtitle_max_lines' => '2']]]], 'is not a card template'];
        yield 'an empty directory' => [['cards' => ['directory' => '']], 'directory'];
        yield 'a negative cache age' => [['cards' => ['cache_max_age' => -1]], 'cache_max_age'];
        yield 'too short a timeout' => [['preview' => ['timeout' => 0.09]], 'timeout'];
        yield 'too long a timeout' => [['preview' => ['timeout' => 60.1]], 'timeout'];
        yield 'too long a fetch' => [['preview' => ['max_duration' => 120.1]], 'max_duration'];
        yield 'too short a fetch' => [['preview' => ['max_duration' => 0.09]], 'max_duration'];
        yield 'too few bytes' => [['preview' => ['max_bytes' => 1023]], 'max_bytes'];
        yield 'too many bytes' => [['preview' => ['max_bytes' => 16_777_217]], 'max_bytes'];
        yield 'too many redirects' => [['preview' => ['max_redirects' => 11]], 'max_redirects'];
        yield 'negative redirects' => [['preview' => ['max_redirects' => -1]], 'max_redirects'];
        yield 'a negative preview cache' => [['preview' => ['cache_ttl' => -1]], 'cache_ttl'];
        yield 'no user agent' => [['preview' => ['user_agent' => '']], 'user_agent'];
        yield 'a user agent that is a number' => [['preview' => ['user_agent' => 5]], 'is not a header value'];
        yield 'a user agent on two lines' => [['preview' => ['user_agent' => "Bot\r\nX-Evil: 1"]], 'is not a header value: one line, without a control character.'];
        yield 'no default template' => [['cards' => ['default_template' => '']], 'default_template'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testTheGoodTemplatesAndValues(): void
    {
        $container = $this->load(['locale' => null, 'cards' => ['templates' => ['default' => [...self::TEMPLATE, 'background' => '#123', 'title_color' => '#123456', 'subtitle_color' => '#12345678', 'accent' => '#abc', 'padding' => 0, 'title_max_size' => 64, 'title_min_size' => 36, 'title_max_lines' => 3, 'subtitle_size' => 30, 'subtitle_max_lines' => 2, 'subtitle_font' => '/f.ttf', 'logo' => '/l.png', 'background_image' => '/b.png']]]]);

        self::assertTrue($container->hasDefinition(CardTemplates::class));
    }

    public function testTheOptionalServicesWithoutTheirs(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['cards' => ['templates' => ['default' => self::TEMPLATE]]]], $container);
        new PhilSocialCardsBundle()->build($container);
        $pass = array_find($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof RequiredServicesPass);
        self::assertInstanceOf(RequiredServicesPass::class, $pass);

        $pass->process($container);
        self::assertFalse($container->hasDefinition(MessengerCardQueue::class));
        self::assertFalse($container->hasDefinition(GenerateSocialCardHandler::class));
        self::assertFalse($container->hasDefinition(MediaShareImages::class));
        self::assertTrue($container->hasDefinition(SocialCards::class));

        $kept = new ContainerBuilder();
        $this->extension()->load([['cards' => ['templates' => ['default' => self::TEMPLATE]]]], $kept);
        $kept->register('messenger.default_bus');
        $kept->register(MediaExtension::class);

        $pass->process($kept);
        self::assertTrue($kept->hasDefinition(MessengerCardQueue::class));
        self::assertTrue($kept->hasDefinition(GenerateSocialCardHandler::class));
        self::assertTrue($kept->hasDefinition(MediaShareImages::class));
        self::assertSame([MessengerCardQueue::class => 'messenger.default_bus', GenerateSocialCardHandler::class => 'messenger.default_bus', MediaShareImages::class => MediaExtension::class], SocialCardsServices::REQUIREMENTS);
    }

    public function testThePassRunsBeforeTheReferencesAreResolved(): void
    {
        $container = new ContainerBuilder();
        new PhilSocialCardsBundle()->build($container);
        $config = $container->getCompilerPassConfig();
        $passes = $config->getPasses();
        $index = array_find_key($passes, static fn (object $pass): bool => $pass instanceof RequiredServicesPass);
        $before = $config->getBeforeOptimizationPasses();

        self::assertNotNull($index);
        self::assertContains($passes[$index], $before);
        self::assertSame(PassConfig::TYPE_BEFORE_OPTIMIZATION, PassConfig::TYPE_BEFORE_OPTIMIZATION);
        $priorities = new ReflectionProperty($config, 'beforeOptimizationPasses')->getValue($config);
        self::assertIsArray($priorities);
        self::assertArrayHasKey(-10, $priorities);
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
        $extension = new PhilSocialCardsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
