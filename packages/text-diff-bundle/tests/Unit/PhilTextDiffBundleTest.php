<?php

declare(strict_types=1);

namespace Phil\TextDiffBundle\Tests\Unit;

use Phil\TextDiff\TextDiff;
use Phil\TextDiff\Tokenizer;
use Phil\TextDiffBundle\Infrastructure\DependencyInjection\TextDiffServices;
use Phil\TextDiffBundle\Infrastructure\Twig\TextDiffExtension;
use Phil\TextDiffBundle\PhilTextDiffBundle;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilTextDiffBundle::class)]
#[CoversClass(TextDiffServices::class)]
final class PhilTextDiffBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_text_diff', $this->extension()->getAlias());
        self::assertSame(['service_container', TextDiff::class, TextDiffExtension::class], array_keys($container->getDefinitions()));
        self::assertEquals([1000, new Reference(Normalizer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(TextDiff::class)->getArguments());
        self::assertTrue($container->getDefinition(TextDiff::class)->isPublic());

        $extension = $container->getDefinition(TextDiffExtension::class);
        self::assertEquals([
            new Reference(TextDiff::class),
            Tokenizer::Words,
            new Reference(Ujo::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference(HtmlRenderer::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            null,
        ], $extension->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $extension->getTags());
        self::assertFalse($extension->isPublic());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['max_edits' => 0, 'tokenizer' => 'paragraphs', 'html_sanitizer' => 'html_sanitizer.sanitizer.app']);

        self::assertSame(0, $container->getDefinition(TextDiff::class)->getArgument(0));
        self::assertSame(Tokenizer::Paragraphs, $container->getDefinition(TextDiffExtension::class)->getArgument(1));
        self::assertEquals(new Reference('html_sanitizer.sanitizer.app'), $container->getDefinition(TextDiffExtension::class)->getArgument(4));
        self::assertSame(Tokenizer::Html, $this->load(['tokenizer' => 'html'])->getDefinition(TextDiffExtension::class)->getArgument(1));
    }

    public function testBadValuesAreRefused(): void
    {
        foreach ([[['max_edits' => -1], 'Should be greater than or equal to 0'], [['tokenizer' => 'letters'], 'Permissible values: "words", "lines", "paragraphs", "html"']] as [$config, $message]) {
            try {
                $this->load($config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheAssetsAreMappedForAssetMapper(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        $container->registerExtension(new FrameworkStub());
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['asset_mapper' => ['paths' => [
            PhilTextDiffBundle::CONTROLLERS_DIR => '@phil/text-diff-bundle',
            PhilTextDiffBundle::STYLES_DIR => 'phil-text-diff',
        ]]]], $container->getExtensionConfig('framework'));
        self::assertFileExists(PhilTextDiffBundle::CONTROLLERS_DIR.'/comparison_controller.js');
        self::assertFileExists(PhilTextDiffBundle::STYLES_DIR.'/text-diff.css');
        self::assertSame(realpath(new PhilTextDiffBundle()->getPath().'/assets/src'), realpath(PhilTextDiffBundle::CONTROLLERS_DIR));
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
        $extension = new PhilTextDiffBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
