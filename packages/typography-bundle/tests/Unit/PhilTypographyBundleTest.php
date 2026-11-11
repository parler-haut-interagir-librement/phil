<?php

declare(strict_types=1);

namespace Phil\TypographyBundle\Tests\Unit;

use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Typographer;
use Phil\Typography\Typographers;
use Phil\TypographyBundle\Infrastructure\DependencyInjection\TypographyServices;
use Phil\TypographyBundle\Infrastructure\DependencyInjection\UjoFilterPass;
use Phil\TypographyBundle\Infrastructure\Twig\TypographyExtension;
use Phil\TypographyBundle\Infrastructure\Ujo\TypographyHtmlFilter;
use Phil\TypographyBundle\PhilTypographyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilTypographyBundle::class)]
#[CoversClass(TypographyServices::class)]
final class PhilTypographyBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $typographers = new Reference(Typographers::class);
        $current = new Reference('translation.locale_switcher', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

        self::assertSame('phil_typography', $this->extension()->getAlias());
        self::assertSame(['service_container', Dictionary::class, Typographers::class, Typographer::class, TypographyExtension::class, TypographyServices::UJO_FILTER], array_keys($container->getDefinitions()));
        self::assertEquals(['%kernel.default_locale%', [], ['pre', 'code', 'kbd', 'script', 'style', 'textarea'], true, new Reference(Dictionary::class)], $container->getDefinition(Typographers::class)->getArguments());
        self::assertTrue($container->getDefinition(Typographers::class)->isPublic());
        self::assertSame([null, []], $container->getDefinition(Dictionary::class)->getArguments());
        self::assertEquals([$typographers, 'for'], $container->getDefinition(Typographer::class)->getFactory());
        self::assertSame([], $container->getDefinition(Typographer::class)->getArguments());
        self::assertTrue($container->getDefinition(Typographer::class)->isPublic());
        self::assertEquals([$typographers, $current], $container->getDefinition(TypographyExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(TypographyExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(TypographyExtension::class)->hasTag('twig.runtime'));
        self::assertSame(TypographyHtmlFilter::class, $container->getDefinition(TypographyServices::UJO_FILTER)->getClass());
        self::assertEquals([$typographers, null, $current], $container->getDefinition(TypographyServices::UJO_FILTER)->getArguments());
        self::assertFalse($container->getDefinition(TypographyServices::UJO_FILTER)->isPublic());
        self::assertSame(TypographyServices::TYPOGRAPHER, 'phil_typography.typographer.');
        self::assertSame(TypographyServices::UJO_RENDERER, 'phil_typography.ujo_renderer');
        self::assertSame(TypographyServices::LOCALE_SWITCHER, 'translation.locale_switcher');
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'default_locale' => 'fr_CA',
            'hyphenation' => false,
            'experimental_hyphenation' => ['mk'],
            'protected_tags' => ['samp'],
            'locales' => ['de_CH' => ['smart_quotes', 'ellipsis'], 'en_GB' => []],
            'ujo' => ['locale' => 'en_GB'],
        ]);
        $typographers = new Reference(Typographers::class);
        $current = new Reference('translation.locale_switcher', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);

        self::assertEquals(['fr_CA', ['de_CH' => ['smart_quotes', 'ellipsis']], ['samp'], false, new Reference(Dictionary::class)], $container->getDefinition(Typographers::class)->getArguments());
        self::assertSame([null, ['mk']], $container->getDefinition(Dictionary::class)->getArguments());
        self::assertSame(['hy', 'mk'], TypographyServices::EXPERIMENTAL);

        foreach (['de_CH', 'en_GB'] as $locale) {
            $definition = $container->getDefinition(TypographyServices::TYPOGRAPHER.$locale);

            self::assertSame(Typographer::class, $definition->getClass());
            self::assertEquals([$typographers, 'for'], $definition->getFactory());
            self::assertSame([$locale], $definition->getArguments());
            self::assertTrue($definition->isPublic());
        }

        self::assertSame(TypographyServices::TYPOGRAPHER.'de_CH', (string) $container->getAlias(Typographer::class.' $deCHTypographer'));
        self::assertSame(TypographyServices::TYPOGRAPHER.'en_GB', (string) $container->getAlias(Typographer::class.' $enGBTypographer'));
        self::assertEquals([$typographers, 'en_GB', $current], $container->getDefinition(TypographyServices::UJO_FILTER)->getArguments());
        self::assertEquals([$typographers, null, $current], $this->load(['default_locale' => 'fr_CA'])->getDefinition(TypographyServices::UJO_FILTER)->getArguments());
    }

    public function testTheUjoFilterCanBeDisabled(): void
    {
        self::assertFalse($this->load(['ujo' => false])->hasDefinition(TypographyServices::UJO_FILTER));
        self::assertFalse($this->load(['ujo' => ['enabled' => false, 'locale' => 'fr']])->hasDefinition(TypographyServices::UJO_FILTER));
    }

    public function testFixerNamesAreChecked(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['locales' => ['fr' => ['kerning']]]);
    }

    public function testOnlyExperimentalPatternsCanBeAskedFor(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['experimental_hyphenation' => ['fr']]);
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilTypographyBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof UjoFilterPass));
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
        $extension = new PhilTypographyBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
