<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Tests\Unit;

use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Text\PlainText;
use Phil\Ujo\Type\BlockType;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Ujo;
use Phil\UjoBundle\Infrastructure\DependencyInjection\UjoServices;
use Phil\UjoBundle\Infrastructure\Form\UjoType;
use Phil\UjoBundle\Infrastructure\Twig\UjoExtension;
use Phil\UjoBundle\Infrastructure\Validator\ValidUjoValidator;
use Phil\UjoBundle\PhilUjoBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilUjoBundle::class)]
#[CoversClass(UjoServices::class)]
final class PhilUjoBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_ujo', $this->extension()->getAlias());
        self::assertSame(['service_container', BlockTypeRegistry::class, Normalizer::class, HtmlRenderer::class, PlainText::class, JsonDecoder::class, Ujo::class, UjoType::class, ValidUjoValidator::class, UjoExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([BlockTypeRegistry::class, 'default'], $container->getDefinition(BlockTypeRegistry::class)->getFactory());
        self::assertEquals([new Reference(BlockTypeRegistry::class), null, null, 8, 10_000, 100_000, 50_000], $container->getDefinition(Normalizer::class)->getArguments());
        self::assertSame([4_194_304, 64], $container->getDefinition(JsonDecoder::class)->getArguments());
        self::assertTrue($container->getDefinition(Ujo::class)->isPublic());
        self::assertSame([HtmlRenderer::class, 'default'], $container->getDefinition(HtmlRenderer::class)->getFactory());
        self::assertEquals([new Reference(Normalizer::class), new Reference(HtmlRenderer::class), new Reference(PlainText::class), new Reference(JsonDecoder::class)], $container->getDefinition(Ujo::class)->getArguments());

        foreach ([UjoType::class, ValidUjoValidator::class, UjoExtension::class] as $service) {
            self::assertEquals([new Reference(Ujo::class)], $container->getDefinition($service)->getArguments());
        }

        self::assertTrue($container->getDefinition(UjoType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(ValidUjoValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(UjoExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(UjoExtension::class)->hasTag('twig.runtime'));
        self::assertSame(4_194_304, UjoServices::MAX_BYTES);
        self::assertSame(8, UjoServices::MAX_DEPTH);
        self::assertSame([10_000, 100_000, 50_000], [UjoServices::MAX_BLOCKS, UjoServices::MAX_HTML_LENGTH, UjoServices::MAX_RAW_LENGTH]);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['allow_raw' => true, 'max_bytes' => 1, 'max_depth' => 1, 'max_blocks' => 2, 'max_html_length' => 3, 'max_raw_length' => 4]);
        $registry = $container->getDefinition(BlockTypeRegistry::class);
        $factory = $registry->getFactory();

        self::assertSame([1, 64], $container->getDefinition(JsonDecoder::class)->getArguments());
        self::assertSame([1, 2, 3, 4], \array_slice($container->getDefinition(Normalizer::class)->getArguments(), 3));
        self::assertIsArray($factory);
        self::assertSame('with', $factory[1]);
        self::assertInstanceOf(Definition::class, $factory[0]);
        self::assertSame([BlockTypeRegistry::class, 'default'], $factory[0]->getFactory());
        $raw = $registry->getArgument(0);
        self::assertInstanceOf(Definition::class, $raw);
        self::assertSame([StandardTypes::class, 'raw'], $raw->getFactory());
        self::assertSame(BlockType::class, $raw->getClass());
    }

    public function testLimitsArePositive(): void
    {
        self::assertSame([1, 1, 1], \array_slice($this->load(['max_blocks' => 1, 'max_html_length' => 1, 'max_raw_length' => 1])->getDefinition(Normalizer::class)->getArguments(), 4));

        foreach (['max_bytes', 'max_depth', 'max_blocks', 'max_html_length', 'max_raw_length'] as $limit) {
            try {
                $this->load([$limit => 0]);
                self::fail($limit);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($limit, $invalidConfigurationException->getMessage());
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
        $extension = new PhilUjoBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
