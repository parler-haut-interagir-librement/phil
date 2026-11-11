<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit;

use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\MarkupServices;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\SmartPunctuationPass;
use Phil\MarkupBundle\Infrastructure\Typography\SmartPunctuationCompiler;
use Phil\Typography\Typographers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(SmartPunctuationPass::class)]
final class SmartPunctuationPassTest extends TestCase
{
    public function testWithoutTheTypographyBundleItBuildsItsOwnTypographers(): void
    {
        $container = $this->container();

        new SmartPunctuationPass()->process($container);

        $definition = $container->getDefinition(MarkupServices::SMART_PUNCTUATION);
        self::assertEquals(new Reference(MarkupServices::TYPOGRAPHERS), $definition->getArgument(1));
        self::assertSame(MarkupFormat::values(), $definition->getArgument(2));
        self::assertSame(Typographers::class, $container->getDefinition(MarkupServices::TYPOGRAPHERS)->getClass());
        self::assertSame(['%kernel.default_locale%', [], ['pre', 'code', 'kbd', 'script', 'style', 'textarea'], false], $container->getDefinition(MarkupServices::TYPOGRAPHERS)->getArguments());
        self::assertFalse($container->getDefinition(MarkupServices::TYPOGRAPHERS)->isPublic());
    }

    public function testTheTypographyBundleLendsItsTypographersAndFixesTheUjoFormats(): void
    {
        $container = $this->container();
        $container->register(Typographers::class, Typographers::class);
        $container->register('phil_typography.ujo_filter', 'stdClass');

        new SmartPunctuationPass()->process($container);

        $definition = $container->getDefinition(MarkupServices::SMART_PUNCTUATION);
        self::assertEquals(new Reference(Typographers::class), $definition->getArgument(1));
        self::assertSame(['html', 'text'], $definition->getArgument(2));
        self::assertFalse($container->hasDefinition(MarkupServices::TYPOGRAPHERS));
        self::assertSame('phil_typography.ujo_filter', SmartPunctuationPass::TYPOGRAPHY_UJO_FILTER);
    }

    /**
     * The formats rendered through UJO are left to the bundle's pass: only those enabled of HTML and plain text stay.
     */
    public function testOnlyTheEnabledFormatsOfTheBundleUjoPassAreLeft(): void
    {
        $container = $this->container(['markdown', 'text', 'shout']);
        $container->register(Typographers::class, Typographers::class);
        $container->register('phil_typography.ujo_filter', 'stdClass');

        new SmartPunctuationPass()->process($container);

        self::assertSame(['text'], $container->getDefinition(MarkupServices::SMART_PUNCTUATION)->getArgument(2));
    }

    public function testTheTypographersOfTheBundleWithoutItsUjoPassFixEveryFormat(): void
    {
        $container = $this->container(['markdown', 'text']);
        $container->register(Typographers::class, Typographers::class);

        new SmartPunctuationPass()->process($container);

        self::assertSame(['markdown', 'text'], $container->getDefinition(MarkupServices::SMART_PUNCTUATION)->getArgument(2));
    }

    public function testNothingHappensWhenTheSmartPunctuationIsOff(): void
    {
        $container = new ContainerBuilder();

        new SmartPunctuationPass()->process($container);

        self::assertFalse($container->hasDefinition(MarkupServices::TYPOGRAPHERS));
    }

    /**
     * @param list<string>|null $formats every format when null
     */
    private function container(?array $formats = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(MarkupServices::SMART_PUNCTUATION, SmartPunctuationCompiler::class)
            ->setArguments([null, new Reference(Typographers::class), $formats ?? MarkupFormat::values(), null, null]);

        return $container;
    }
}
