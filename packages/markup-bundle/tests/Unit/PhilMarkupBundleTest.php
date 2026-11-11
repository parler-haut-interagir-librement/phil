<?php

declare(strict_types=1);

namespace Phil\MarkupBundle\Tests\Unit;

use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\HtmlPurifier;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupCompiler;
use Phil\Markup\MarkupFormat;
use Phil\MarkupBundle\Infrastructure\Conversion\UjoConverter;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\MarkupServices;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\MediaHostsPass;
use Phil\MarkupBundle\Infrastructure\DependencyInjection\SmartPunctuationPass;
use Phil\MarkupBundle\Infrastructure\Form\MarkupFormatType;
use Phil\MarkupBundle\Infrastructure\Format\MarkupFormats;
use Phil\MarkupBundle\Infrastructure\Twig\MarkupExtension;
use Phil\MarkupBundle\Infrastructure\Typography\SmartPunctuationCompiler;
use Phil\MarkupBundle\PhilMarkupBundle;
use Phil\Typography\Typographers;
use Phil\Ujo\Html\HtmlRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilMarkupBundle::class)]
#[CoversClass(MarkupServices::class)]
final class PhilMarkupBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_markup', $this->extension()->getAlias());
        self::assertSame(['service_container', HtmlRenderer::class, DefaultCompiler::class, SanitizerPurifier::class, Markup::class, MarkupFormats::class, UjoConverter::class, MarkupServices::SMART_PUNCTUATION, MarkupFormatType::class, MarkupExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([HtmlRenderer::class, 'default'], $container->getDefinition(HtmlRenderer::class)->getFactory());
        self::assertEquals([new Reference(HtmlRenderer::class), []], $container->getDefinition(DefaultCompiler::class)->getArguments());
        self::assertSame(DefaultCompiler::class, (string) $container->getAlias(MarkupCompiler::class));
        self::assertSame(SanitizerPurifier::class, (string) $container->getAlias(HtmlPurifier::class));
        self::assertSame([SanitizerPurifier::class, 'defaultPolicies'], $container->getDefinition(SanitizerPurifier::class)->getFactory());
        self::assertEquals([new Reference(MarkupCompiler::class), new Reference(HtmlPurifier::class)], $container->getDefinition(Markup::class)->getArguments());
        self::assertTrue($container->getDefinition(Markup::class)->isPublic());
        self::assertSame([MarkupFormat::values(), 'markdown', Level::All, [], []], $container->getDefinition(MarkupFormats::class)->getArguments());
        self::assertTrue($container->getDefinition(MarkupFormats::class)->isPublic());
        self::assertEquals([new Reference(DefaultCompiler::class), new Reference(MarkupFormats::class)], $container->getDefinition(UjoConverter::class)->getArguments());
        self::assertTrue($container->getDefinition(UjoConverter::class)->isPublic());
        self::assertEquals([new Reference(MarkupFormats::class)], $container->getDefinition(MarkupFormatType::class)->getArguments());
        self::assertTrue($container->getDefinition(MarkupFormatType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(Markup::class), new Reference(HtmlPurifier::class), new Reference(MarkupFormats::class), new Reference(UjoConverter::class)], $container->getDefinition(MarkupExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(MarkupExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(MarkupExtension::class)->hasTag('twig.runtime'));
        self::assertSame(['basic', 'forum', 'all'], MarkupServices::LEVELS);
        self::assertSame(SmartPunctuationCompiler::class, $container->getDefinition(MarkupServices::SMART_PUNCTUATION)->getClass());
        self::assertSame([MarkupCompiler::class, null, 0], $container->getDefinition(MarkupServices::SMART_PUNCTUATION)->getDecoratedService());
        self::assertEquals(
            [new Reference('phil_markup.smart_punctuation.inner'), new Reference(Typographers::class), MarkupFormat::values(), null, new Reference('translation.locale_switcher', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)],
            $container->getDefinition(MarkupServices::SMART_PUNCTUATION)->getArguments(),
        );
        self::assertSame([null], $container->getDefinition(SanitizerPurifier::class)->getArguments());
        self::assertNull($container->getParameter(MediaHostsPass::PARAMETER));
        self::assertSame([['cdn.example.com', 'img.example.org']], $this->load(['media_hosts' => ['cdn.example.com', 'img.example.org']])->getDefinition(SanitizerPurifier::class)->getArguments());
        self::assertSame([[]], $this->load(['media_hosts' => []])->getDefinition(SanitizerPurifier::class)->getArguments());
    }

    public function testTheFormatsAndTheLevels(): void
    {
        self::assertSame(
            [['bbcode', 'text'], 'bbcode', Level::Basic, ['bbcode' => Level::Forum], []],
            $this->load(['formats' => ['bbcode', 'bbcode', 'text'], 'levels' => ['bbcode' => 'forum'], 'default_level' => 'basic'])->getDefinition(MarkupFormats::class)->getArguments(),
        );
        self::assertSame('text', $this->load(['formats' => ['bbcode', 'text'], 'default_format' => 'text'])->getDefinition(MarkupFormats::class)->getArgument(1));
        self::assertSame('ujo', $this->load(['default_format' => 'ujo'])->getDefinition(MarkupFormats::class)->getArgument(1));
        self::assertSame(['ujo' => Level::Basic], $this->load(['formats' => ['text'], 'levels' => ['ujo' => 'basic']])->getDefinition(MarkupFormats::class)->getArgument(3));
    }

    public function testTheSmartPunctuationIsConfigured(): void
    {
        $container = $this->load(['formats' => ['bbcode', 'text'], 'smart_punctuation' => ['locale' => 'fr_CA']]);

        self::assertSame(['bbcode', 'text'], $container->getDefinition(MarkupServices::SMART_PUNCTUATION)->getArgument(2));
        self::assertSame('fr_CA', $container->getDefinition(MarkupServices::SMART_PUNCTUATION)->getArgument(3));
        self::assertFalse($this->load(['smart_punctuation' => false])->hasDefinition(MarkupServices::SMART_PUNCTUATION));
        self::assertFalse($this->load(['smart_punctuation' => ['enabled' => false, 'locale' => 'fr']])->hasDefinition(MarkupServices::SMART_PUNCTUATION));
        self::assertSame('phil_markup.smart_punctuation', MarkupServices::SMART_PUNCTUATION);
        self::assertSame('phil_markup.typographers', MarkupServices::TYPOGRAPHERS);
        self::assertSame('translation.locale_switcher', MarkupServices::LOCALE_SWITCHER);
    }

    public function testTheBundleAddsItsPasses(): void
    {
        $container = new ContainerBuilder();
        new PhilMarkupBundle()->build($container);
        $passes = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();

        self::assertCount(1, array_filter($passes, static fn (object $pass): bool => $pass instanceof MediaHostsPass));
        self::assertCount(1, array_filter($passes, static fn (object $pass): bool => $pass instanceof SmartPunctuationPass));
    }

    public function testTheFormatsOfALaterFileReplaceThoseOfAnEarlierOne(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['formats' => ['bbcode', 'text']], ['formats' => ['markdown']]], $container);

        self::assertSame(['markdown'], $container->getDefinition(MarkupFormats::class)->getArgument(0));
    }

    /**
     * A format an add-on brings is enabled after the built-in ones, read by the service of its reader, labelled, and
     * may be named by the formats, the default format and the levels.
     */
    public function testTheFormatsOfAddOns(): void
    {
        $added = ['added_formats' => ['shout' => ['label' => 'Shout', 'reader' => 'app.shout_reader'], 'whisper' => ['label' => 'Whisper', 'reader' => 'app.whisper_reader']]];
        $container = $this->load($added);

        self::assertEquals([new Reference(HtmlRenderer::class), ['shout' => new Reference('app.shout_reader'), 'whisper' => new Reference('app.whisper_reader')]], $container->getDefinition(DefaultCompiler::class)->getArguments());
        self::assertSame([[...MarkupFormat::values(), 'shout', 'whisper'], 'markdown', Level::All, [], ['shout' => 'Shout', 'whisper' => 'Whisper']], $container->getDefinition(MarkupFormats::class)->getArguments());
        self::assertSame([...MarkupFormat::values(), 'shout', 'whisper'], $container->getDefinition(MarkupServices::SMART_PUNCTUATION)->getArgument(2));
        self::assertSame(
            [['shout', 'text'], 'shout', Level::All, ['whisper' => Level::Basic], ['shout' => 'Shout', 'whisper' => 'Whisper']],
            $this->load([...$added, 'formats' => ['shout', 'text'], 'default_format' => 'shout', 'levels' => ['whisper' => 'basic']])->getDefinition(MarkupFormats::class)->getArguments(),
        );

        $container = new ContainerBuilder();
        $this->extension()->load([['added_formats' => ['shout' => ['label' => 'Shout', 'reader' => 'a']]], ['added_formats' => ['whisper' => ['label' => 'Whisper', 'reader' => 'b']]]], $container);
        self::assertSame(['shout' => 'Shout', 'whisper' => 'Whisper'], $container->getDefinition(MarkupFormats::class)->getArgument(4));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'default not enabled' => [['formats' => ['bbcode'], 'default_format' => 'markdown'], 'The default format must be one of the enabled formats; "markdown" is not.'];
        yield 'default unknown' => [['default_format' => 'latex'], 'The default format must be one of the enabled formats; "latex" is not.'];
        yield 'unknown format' => [['formats' => ['latex', 'bbcode', 'rtf']], 'The formats are among markdown, html, bbcode, mediawiki, dokuwiki, creole, text, ujo; "latex", "rtf" is not.'];
        yield 'unknown level' => [['levels' => ['bbcode' => 'none']], 'is not allowed'];
        yield 'unknown default level' => [['default_level' => 'none'], 'is not allowed'];
        yield 'levels of an unknown format' => [['levels' => ['latex' => 'forum']], 'The levels are given by format, one of markdown, html, bbcode, mediawiki, dokuwiki, creole, text, ujo; "latex" is not.'];
        yield 'added format taken' => [['added_formats' => ['markdown' => ['label' => 'M', 'reader' => 'r'], 'Shout' => ['label' => 'S', 'reader' => 'r']]], 'An added format is named by 1 to 16 lower-case letters, digits and hyphens, starting with a letter, and is not a built-in format; "markdown", "Shout" is not.'];
        yield 'added format of digits' => [['added_formats' => ['123' => ['label' => 'N', 'reader' => 'r']]], '"123" is not.'];
        yield 'added format too long' => [['added_formats' => ['abcdefghijklmnopq' => ['label' => 'A', 'reader' => 'r']]], '"abcdefghijklmnopq" is not.'];
        yield 'added format without its reader' => [['added_formats' => ['shout' => ['label' => 'S']]], 'The child config "reader" under "phil_markup.added_formats.shout" must be configured'];
        yield 'added format with an empty label' => [['added_formats' => ['shout' => ['label' => '', 'reader' => 'r']]], 'cannot contain an empty value'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refused')]
    public function testAConfigurationThatDoesNotHoldIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testBadMediaHostsAreRefused(): void
    {
        foreach ([['https://cdn.example.com'], 'cdn.example.com', ['a' => 'cdn.example.com'], [''], ['CDN.example.com'], [12], ["cdn.example.com\n"]] as $hosts) {
            try {
                $this->load(['media_hosts' => $hosts]);
                self::fail(json_encode($hosts, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('The media hosts are null or a list of host names', $invalidConfigurationException->getMessage());
            }
        }
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
        $extension = new PhilMarkupBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
