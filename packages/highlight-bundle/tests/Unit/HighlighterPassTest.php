<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Unit;

use Closure;
use Phil\Highlight\Highlighter;
use Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlighterPass;
use Phil\HighlightBundle\Infrastructure\DependencyInjection\HighlightServices;
use Phil\HighlightBundle\PhilHighlightBundle;
use Phil\Ujo\Html\HtmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(HighlighterPass::class)]
final class HighlighterPassTest extends TestCase
{
    public function testTheTaggedLanguagesAndTheAliasesAreAdded(): void
    {
        $container = $this->container(['aliases' => ['loud' => 'shout', 'caps' => 'shout', 'vue' => 'html', '1' => 'html']]);
        $container->register('app.shout', ShoutLanguage::class)->addTag(HighlightServices::LANGUAGE_TAG);
        $container->register('app.phpish', ShoutLanguage::class)->setArguments(['php', []])->addTag(HighlightServices::LANGUAGE_TAG);

        $highlighter = $this->highlighter($container);

        self::assertSame('<span class="hl-keyword">a b</span>', $highlighter->highlight('a b', 'shout'));
        self::assertSame('<span class="hl-keyword">a b</span>', $highlighter->highlight('a b', 'yell'));
        self::assertSame('<span class="hl-keyword">a b</span>', $highlighter->highlight('a b', 'loud'));
        self::assertSame('<span class="hl-keyword">a b</span>', $highlighter->highlight('a b', 'caps'));
        self::assertSame('<span class="hl-keyword">$a</span>', $highlighter->highlight('$a', 'php'));
        self::assertSame('<span class="hl-punctuation">&lt;</span><span class="hl-tag">p</span><span class="hl-punctuation">&gt;</span>', $highlighter->highlight('<p>', 'vue'));
        self::assertSame('html', $highlighter->language('vue')->name());
        self::assertSame('html', $highlighter->language('1')->name());
        self::assertTrue($container->getDefinition(Highlighter::class)->isPublic());
    }

    public function testATaggedLanguageRegisteredLaterReplacesAnEarlierOne(): void
    {
        $container = $this->container([]);
        $container->register('app.first', ShoutLanguage::class)->setArguments(['first', ['same']])->addTag(HighlightServices::LANGUAGE_TAG);
        $container->register('app.second', ShoutLanguage::class)->setArguments(['second', ['same']])->addTag(HighlightServices::LANGUAGE_TAG);

        self::assertSame('second', $this->highlighter($container)->language('same')->name());
    }

    public function testWithoutLanguagesOrAliasesTheShippedOnesServe(): void
    {
        $container = $this->container([]);

        self::assertSame(Highlighter::default()->languages(), $this->highlighter($container)->languages());
    }

    public function testTheUjoRendererIsDecorated(): void
    {
        $container = $this->container([]);
        $container->register(HtmlRenderer::class, HtmlRenderer::class)->setFactory([HtmlRenderer::class, 'default']);

        new HighlighterPass()->process($container);

        $decorator = $container->getDefinition(HighlightServices::UJO_RENDERER);
        self::assertSame([HtmlRenderer::class, null, 0], $decorator->getDecoratedService());
        self::assertEquals([new Reference(HighlightServices::UJO_RENDERER.'.inner'), 'withCodeHighlighter'], $decorator->getFactory());
        self::assertEquals([new Reference(HighlightServices::CODE_HIGHLIGHTER)], $decorator->getArguments());
        self::assertSame(HtmlRenderer::class, $decorator->getClass());
        self::assertSame('phil_highlight.ujo_renderer', HighlightServices::UJO_RENDERER);
    }

    public function testWithoutTheUjoRendererTheCodeHighlighterIsRemoved(): void
    {
        $container = $this->container([]);

        new HighlighterPass()->process($container);

        self::assertFalse($container->hasDefinition(HighlightServices::CODE_HIGHLIGHTER));
        self::assertFalse($container->hasDefinition(HighlightServices::UJO_RENDERER));
    }

    public function testWithTheUjoOptionOffNothingIsDecorated(): void
    {
        $container = $this->container(['ujo' => false]);
        $container->register(HtmlRenderer::class, HtmlRenderer::class);

        new HighlighterPass()->process($container);

        self::assertFalse($container->hasDefinition(HighlightServices::UJO_RENDERER));
    }

    public function testWithoutTheBundleNothingHappens(): void
    {
        $container = new ContainerBuilder();

        new HighlighterPass()->process($container);

        self::assertFalse($container->hasDefinition(Highlighter::class));
    }

    public function testTheCodeHighlighterIsTheHighlighter(): void
    {
        $container = $this->container([]);
        $container->register(HtmlRenderer::class, HtmlRenderer::class)->setFactory([HtmlRenderer::class, 'default']);
        $container->getDefinition(HighlightServices::CODE_HIGHLIGHTER)->setPublic(true);
        $container->compile(true);

        $closure = $container->get(HighlightServices::CODE_HIGHLIGHTER);
        self::assertInstanceOf(Closure::class, $closure);
        self::assertSame('<span class="hl-number">1</span>', $closure('1', 'php'));
        self::assertNull($closure('1', 'cobol'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function container(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilHighlightBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $extension->load([$config], $container);

        return $container;
    }

    private function highlighter(ContainerBuilder $container): Highlighter
    {
        new HighlighterPass()->process($container);
        $container->compile(true);
        $highlighter = $container->get(Highlighter::class);
        self::assertInstanceOf(Highlighter::class, $highlighter);

        return $highlighter;
    }
}
