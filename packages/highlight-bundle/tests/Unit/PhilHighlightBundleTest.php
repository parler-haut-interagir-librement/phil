<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Unit;

use Closure;
use Phil\Highlight\Highlighter;
use Phil\Highlight\Language;
use Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlighterPass;
use Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlightServices;
use Phil\HighlightBundle\Infrastructure\Twig\HighlightExtension;
use Phil\HighlightBundle\PhilHighlightBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilHighlightBundle::class)]
#[CoversClass(HighlightServices::class)]
final class PhilHighlightBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_highlight', $this->extension()->getAlias());
        self::assertSame(['service_container', Highlighter::class, HighlightServices::CODE_HIGHLIGHTER, HighlightExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([Highlighter::class, 'default'], $container->getDefinition(Highlighter::class)->getFactory());
        self::assertTrue($container->getDefinition(Highlighter::class)->isPublic());
        self::assertSame([], $container->getParameter(HighlightServices::ALIASES));
        self::assertSame(Closure::class, $container->getDefinition(HighlightServices::CODE_HIGHLIGHTER)->getClass());
        self::assertSame([Closure::class, 'fromCallable'], $container->getDefinition(HighlightServices::CODE_HIGHLIGHTER)->getFactory());
        self::assertEquals([[new Reference(Highlighter::class), 'highlight']], $container->getDefinition(HighlightServices::CODE_HIGHLIGHTER)->getArguments());
        self::assertFalse($container->getDefinition(HighlightServices::CODE_HIGHLIGHTER)->isPublic());
        self::assertEquals([new Reference(Highlighter::class)], $container->getDefinition(HighlightExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(HighlightExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(HighlightExtension::class)->hasTag('twig.runtime'));
        self::assertSame([HighlightServices::LANGUAGE_TAG => [[]]], $container->getAutoconfiguredInstanceof()[Language::class]->getTags());
        self::assertSame('phil_highlight.language', HighlightServices::LANGUAGE_TAG);
        self::assertSame('phil_highlight.code_highlighter', HighlightServices::CODE_HIGHLIGHTER);
        self::assertSame('phil_highlight.aliases', HighlightServices::ALIASES);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['aliases' => ['vue' => 'html', 'c++17' => 'cpp', 'x_1.#-' => 'shout'], 'ujo' => false]);

        self::assertSame(['vue' => 'html', 'c++17' => 'cpp', 'x_1.#-' => 'shout'], $container->getParameter(HighlightServices::ALIASES));
        self::assertFalse($container->hasDefinition(HighlightServices::CODE_HIGHLIGHTER));
        self::assertFalse($this->load(['ujo' => ['enabled' => false]])->hasDefinition(HighlightServices::CODE_HIGHLIGHTER));
    }

    public function testBadNamesAreRefused(): void
    {
        foreach ([['Vue' => 'html'], ['vue' => 'HTML'], ['v ue' => 'html'], ['vue' => ''], [str_repeat('a', 33) => 'html'], ['vue' => str_repeat('a', 33)], ['vue' => "html\n"], ['vue' => 12]] as $aliases) {
            try {
                $this->load(['aliases' => $aliases]);
                self::fail(json_encode($aliases, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertMatchesRegularExpression('/language is named in lower case|alias is written in lower case/', $invalidConfigurationException->getMessage());
            }
        }

        self::assertSame([1 => 'html'], $this->load(['aliases' => ['1' => 'html']])->getParameter(HighlightServices::ALIASES));
        self::assertTrue(HighlightServices::isName(str_repeat('a', 32)));
        self::assertTrue(HighlightServices::isName('c++'));
        self::assertFalse(HighlightServices::isName(null));
    }

    public function testTheStylesheetIsMappedForAssetMapper(): void
    {
        $container = $this->prepend();
        $bundle = new PhilHighlightBundle();

        self::assertSame([['asset_mapper' => ['paths' => [$bundle->getPath().'/assets/styles' => 'phil-highlight']]]], $container->getExtensionConfig('framework'));
        self::assertFileExists($bundle->getPath().'/assets/styles/highlight.css');
        self::assertSame('phil-highlight', HighlightServices::ASSET_NAMESPACE);
    }

    public function testWithoutFrameworkBundleNothingIsMapped(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $extension->prepend($container);

        self::assertFalse($container->hasExtension('framework'));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilHighlightBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof HighlighterPass));
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

    private function prepend(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        $container->registerExtension(new FrameworkStub());
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilHighlightBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
