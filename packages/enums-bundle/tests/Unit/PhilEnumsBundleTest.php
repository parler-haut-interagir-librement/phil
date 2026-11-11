<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit;

use Doctrine\DBAL\Types\Type;
use Phil\EnumsBundle\Infrastructure\Console\CheckSetColumnsCommand;
use Phil\EnumsBundle\Infrastructure\Console\DebugEnumsCommand;
use Phil\EnumsBundle\Infrastructure\Console\ExportIcuCommand;
use Phil\EnumsBundle\Infrastructure\Console\ExportTypeScriptCommand;
use Phil\EnumsBundle\Infrastructure\Doctrine\EnumSetStorage;
use Phil\EnumsBundle\Infrastructure\Doctrine\EnumSetType;
use Phil\EnumsBundle\Infrastructure\ExpressionLanguage\EnumsExpressionProvider;
use Phil\EnumsBundle\Infrastructure\Form\EnumSetType as EnumSetFormType;
use Phil\EnumsBundle\Infrastructure\Form\EnumTypeExtension;
use Phil\EnumsBundle\Infrastructure\Http\ParsedEnumValueResolver;
use Phil\EnumsBundle\Infrastructure\Maker\MakeEnum;
use Phil\EnumsBundle\Infrastructure\OpenApi\ApiPlatformEnumPropertyMetadataFactory;
use Phil\EnumsBundle\Infrastructure\OpenApi\NelmioEnumModelDescriber;
use Phil\EnumsBundle\Infrastructure\Serializer\EnumsNormalizer;
use Phil\EnumsBundle\Infrastructure\Translation\EnumTranslationExtractor;
use Phil\EnumsBundle\Infrastructure\Twig\EnumsExtension;
use Phil\EnumsBundle\Infrastructure\Validator\EnumCaseValidator;
use Phil\EnumsBundle\Infrastructure\Validator\EnumSetOfValidator;
use Phil\EnumsBundle\Infrastructure\Validator\TransitionValidator;
use Phil\EnumsBundle\PhilEnumsBundle;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\Delivery;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilEnumsBundle::class)]
final class PhilEnumsBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilEnums(): void
    {
        self::assertSame('phil_enums', new PhilEnumsBundle()->getContainerExtension()?->getAlias());
    }

    public function testTheFormExtensionIsTagged(): void
    {
        self::assertTrue($this->load()->getDefinition(EnumTypeExtension::class)->hasTag('form.type_extension'));
    }

    public function testTheValidatorIsTagged(): void
    {
        self::assertTrue($this->load()->getDefinition(EnumCaseValidator::class)->hasTag('validator.constraint_validator'));
    }

    public function testTheTwigExtensionIsTaggedAndTakesAnOptionalTranslator(): void
    {
        $twig = $this->load()->getDefinition(EnumsExtension::class);

        self::assertTrue($twig->hasTag('twig.attribute_extension'));
        self::assertTrue($twig->hasTag('twig.runtime'));

        $translator = $twig->getArgument(0);
        self::assertInstanceOf(Reference::class, $translator);
        self::assertSame('translator', (string) $translator);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $translator->getInvalidBehavior());
    }

    public function testTheFormTypeOfSetsIsTagged(): void
    {
        self::assertTrue($this->load()->getDefinition(EnumSetFormType::class)->hasTag('form.type'));
    }

    public function testTheExtractorIsTaggedWithItsAlias(): void
    {
        self::assertSame([['alias' => 'phil_enums']], $this->load()->getDefinition(EnumTranslationExtractor::class)->getTag('translation.extractor'));
    }

    public function testTheCommandsLookInTheConfiguredPaths(): void
    {
        $default = $this->load();
        $configured = $this->load(['paths' => ['/app/src/Enum', '/app/lib']]);

        foreach ([DebugEnumsCommand::class, ExportTypeScriptCommand::class] as $command) {
            self::assertTrue($default->getDefinition($command)->hasTag('console.command'));
            self::assertSame(['%kernel.project_dir%/src'], $default->getDefinition($command)->getArgument(0));
            self::assertSame(['/app/src/Enum', '/app/lib'], $configured->getDefinition($command)->getArgument(0));
            $this->assertOptionalTranslator($default->getDefinition($command)->getArgument(1));
        }
    }

    public function testTheNormalizerComesBeforeSymfonysEnumNormalizer(): void
    {
        $normalizer = $this->load()->getDefinition(EnumsNormalizer::class);

        self::assertSame([['before' => ['serializer.normalizer.translatable', 'serializer.normalizer.backed_enum', 'serializer.normalizer.object']]], $normalizer->getTag('serializer.normalizer'));
        $this->assertOptionalTranslator($normalizer->getArgument(0));
    }

    public function testTheSetTypesAreConfiguredWithJsonByDefault(): void
    {
        self::assertSame([], $this->load()->getParameter('phil_enums.doctrine.sets'));
        self::assertSame(
            ['access' => ['enum' => Access::class, 'storage' => 'bitmask'], 'shapes' => ['enum' => Shape::class, 'storage' => 'json']],
            $this->load(['doctrine' => ['sets' => ['access' => ['enum' => Access::class, 'storage' => 'bitmask'], 'shapes' => ['enum' => Shape::class]]]])->getParameter('phil_enums.doctrine.sets'),
        );
    }

    public function testASetTypeNeedsAnEnum(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"stdClass" is not an enum.');

        $this->load(['doctrine' => ['sets' => ['bad' => ['enum' => 'stdClass']]]]);
    }

    public function testASetTypeNeedsAKnownStorage(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['doctrine' => ['sets' => ['bad' => ['enum' => Access::class, 'storage' => 'csv']]]]);
    }

    public function testBootRegistersTheSetTypesOnce(): void
    {
        $container = $this->load(['doctrine' => ['sets' => ['phil_test_access' => ['enum' => Access::class, 'storage' => 'bitmask']]]]);
        $bundle = new PhilEnumsBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        $type = Type::getType('phil_test_access');
        self::assertInstanceOf(EnumSetType::class, $type);
        self::assertSame(Access::class, $type->enum());
        self::assertSame(EnumSetStorage::Bitmask, $type->storage());

        $bundle->boot();
        self::assertSame($type, Type::getType('phil_test_access'));
    }

    public function testTheSetValidatorIsTagged(): void
    {
        self::assertTrue($this->load()->getDefinition(EnumSetOfValidator::class)->hasTag('validator.constraint_validator'));
        $transition = $this->load()->getDefinition(TransitionValidator::class);
        self::assertTrue($transition->hasTag('validator.constraint_validator'));
        self::assertEquals([new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $transition->getArguments());
    }

    public function testTheIcuAndCheckCommandsAreRegistered(): void
    {
        $container = $this->load();

        self::assertTrue($container->getDefinition(ExportIcuCommand::class)->hasTag('console.command'));
        $this->assertOptionalTranslator($container->getDefinition(ExportIcuCommand::class)->getArgument(0));
        self::assertTrue($container->getDefinition(CheckSetColumnsCommand::class)->hasTag('console.command'));
        $doctrine = $container->getDefinition(CheckSetColumnsCommand::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $doctrine);
        self::assertSame('doctrine', (string) $doctrine);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $doctrine->getInvalidBehavior());
    }

    public function testTheResolverIsTargetedByItsName(): void
    {
        self::assertSame([['name' => ParsedEnumValueResolver::class]], $this->load()->getDefinition(ParsedEnumValueResolver::class)->getTag('controller.argument_value_resolver'));
    }

    public function testTheExpressionFunctionsReachSecurityRoutingAndValidation(): void
    {
        $provider = $this->load()->getDefinition(EnumsExpressionProvider::class);

        foreach (['security', 'routing', 'validator'] as $component) {
            self::assertTrue($provider->hasTag($component.'.expression_language_provider'));
        }
    }

    public function testTheNelmioDescriberComesBeforeNelmiosOwn(): void
    {
        self::assertSame([['before' => 'nelmio_api_doc.model_describers.enum']], $this->load()->getDefinition(NelmioEnumModelDescriber::class)->getTag('nelmio_api_doc.model_describer'));
    }

    public function testTheApiPlatformFactoryDecoratesThePropertyMetadataFactory(): void
    {
        $definition = $this->load()->getDefinition(ApiPlatformEnumPropertyMetadataFactory::class);

        self::assertSame(['api_platform.metadata.property.metadata_factory', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE], $definition->getDecoratedService());
        self::assertSame([['priority' => null, 'around' => 'api_platform.json_schema.metadata.property.metadata_factory.schema', 'within' => 'api_platform.metadata.property.metadata_factory.cached']], $definition->getTag('container.decoration_order'));

        $inner = $definition->getArgument(0);
        self::assertInstanceOf(Reference::class, $inner);
        self::assertSame('.inner', (string) $inner);
    }

    public function testMakeEnumIsAMakerCommand(): void
    {
        self::assertTrue($this->load()->getDefinition(MakeEnum::class)->hasTag('maker.command'));
    }

    public function testTheWorkflowsArePrependedToFrameworkBundle(): void
    {
        $builder = new ContainerBuilder();
        $builder->registerExtension(new FrameworkExtension());
        $builder->prependExtensionConfig('phil_enums', ['workflows' => ['delivery' => ['enum' => Delivery::class, 'supports' => ['App\Parcel']]]]);
        $builder->prependExtensionConfig('phil_enums', ['paths' => ['/app/src']]);

        $bundle = new PhilEnumsBundle();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        $framework = $builder->getExtensionConfig('framework');
        self::assertCount(1, $framework);
        self::assertIsArray($framework[0]);
        $workflows = $framework[0]['workflows'] ?? null;
        self::assertIsArray($workflows);
        self::assertSame(['delivery'], array_keys($workflows));
        self::assertIsArray($workflows['delivery']);
        self::assertSame(['type' => 'method', 'property' => 'status'], $workflows['delivery']['marking_store']);
    }

    public function testWithoutWorkflowsNothingIsPrepended(): void
    {
        $builder = new ContainerBuilder();
        $extension = new PhilEnumsBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($builder);

        self::assertSame([], $builder->getExtensionConfig('framework'));
    }

    public function testAWorkflowNeedsABackedEnumAndASubject(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('is not a backed enum.');

        $this->load(['workflows' => ['shape' => ['enum' => Shape::class, 'supports' => ['App\Thing']]]]);
    }

    public function testTheWorkflowPropertyDefaultsToStatus(): void
    {
        $this->load(['workflows' => ['delivery' => ['enum' => Delivery::class, 'supports' => ['App\Parcel']]]]);

        $this->expectException(InvalidConfigurationException::class);

        $this->load(['workflows' => ['delivery' => ['enum' => Delivery::class, 'supports' => []]]]);
    }

    private function assertOptionalTranslator(mixed $translator): void
    {
        self::assertInstanceOf(Reference::class, $translator);
        self::assertSame('translator', (string) $translator);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $translator->getInvalidBehavior());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilEnumsBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}
