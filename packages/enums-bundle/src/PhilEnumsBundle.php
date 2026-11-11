<?php

declare(strict_types=1);

namespace Phil\EnumsBundle;

use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use BackedEnum;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\ModelDescriber\ModelDescriberInterface;
use Override;
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
use Phil\EnumsBundle\Infrastructure\Workflow\EnumWorkflows;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use UnitEnum;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * The Symfony integration of `phil/enums`.
 *
 * The form type extension and type, the validators of the `EnumCase` and `EnumSetOf` constraints and the Twig
 * functions are registered as soon as the bundle is, and act only on the enums and options that ask for them. The
 * rest is registered when its component is installed: the translation extractor with `symfony/translation`, the
 * commands with `symfony/console`, the normalizer with `symfony/serializer`, the `EnumSet` column types with
 * `doctrine/dbal` and their check with `doctrine/orm`, the `#[ParseEnum]` resolver with `symfony/http-kernel`, the
 * expression functions with `symfony/expression-language`, the state machines with `symfony/workflow`, the
 * enum schemas with NelmioApiDocBundle and API Platform, and `make:enum` with MakerBundle.
 *
 * ```yaml
 * phil_enums:
 *     paths: ['%kernel.project_dir%/src']   # where the commands look for enums
 *     doctrine:
 *         sets:
 *             permissions: { enum: App\Permission, storage: bitmask }
 *     workflows:
 *         order: { enum: App\OrderStatus, supports: [App\Order], property: status }
 * ```
 */
final class PhilEnumsBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_enums';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('paths')
                    ->info('The directories and files the commands look for enums in.')
                    ->scalarPrototype()->end()
                    ->defaultValue(['%kernel.project_dir%/src'])
                ->end()
                ->arrayNode('doctrine')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('sets')
                            ->info('DBAL types that store an EnumSet, by type name.')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('enum')
                                        ->isRequired()
                                        ->validate()
                                            ->ifTrue(static fn (mixed $enum): bool => !\is_string($enum) || !enum_exists($enum))
                                            ->thenInvalid('%s is not an enum.')
                                        ->end()
                                    ->end()
                                    ->enumNode('storage')
                                        ->values(array_map(static fn (EnumSetStorage $storage): string => $storage->value, EnumSetStorage::cases()))
                                        ->defaultValue(EnumSetStorage::Json->value)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('workflows')
                    ->info('Symfony state machines built from the #[TransitionsTo] of an enum, by workflow name.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('enum')
                                ->isRequired()
                                ->validate()
                                    ->ifTrue(static fn (mixed $enum): bool => !\is_string($enum) || !is_subclass_of($enum, BackedEnum::class))
                                    ->thenInvalid('%s is not a backed enum.')
                                ->end()
                            ->end()
                            ->arrayNode('supports')
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->scalarPrototype()->end()
                            ->end()
                            ->scalarNode('property')->defaultValue('status')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * Gives FrameworkBundle the state machines the configuration declares, built from the enums' transitions, so
     * that the component validates and wires them as its own.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!interface_exists(WorkflowInterface::class)) {
            return;
        }

        // The bundle's own tree, read before its extension loads: the workflows must reach FrameworkBundle first.
        $configuration = new Configuration($this, $builder, $this->extensionAlias);

        /** @var array{workflows: array<string, array{enum: class-string<BackedEnum>, supports: list<class-string>, property: string}>} $config */
        $config = new Processor()->processConfiguration($configuration, $builder->getExtensionConfig($this->extensionAlias));
        $workflows = array_map(
            static fn (array $workflow): array => EnumWorkflows::definition($workflow['enum'], $workflow['supports'], $workflow['property']),
            $config['workflows'],
        );

        if ([] !== $workflows) {
            $container->extension('framework', ['workflows' => $workflows]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{paths: list<string>, doctrine: array{sets: array<string, array{enum: class-string<UnitEnum>, storage: string}>}, workflows: array<string, mixed>} $config */
        $container->parameters()->set('phil_enums.doctrine.sets', $config['doctrine']['sets']);

        $services = $container->services();

        $services->set(EnumTypeExtension::class)
            ->tag('form.type_extension');

        $services->set(EnumSetFormType::class)
            ->tag('form.type');

        foreach ([EnumCaseValidator::class, EnumSetOfValidator::class] as $validator) {
            $services->set($validator)
                ->tag('validator.constraint_validator');
        }

        // Doctrine is optional: without it, a `Transition` reads the previous case from the object only.
        $services->set(TransitionValidator::class)
            ->args([service('doctrine')->nullOnInvalid()])
            ->tag('validator.constraint_validator');

        // The translator is optional everywhere: without it, labels are shown as written.
        $services->set(EnumsExtension::class)
            ->args([service('translator')->nullOnInvalid()])
            ->tag('twig.attribute_extension')
            ->tag('twig.runtime');

        if (interface_exists(ExtractorInterface::class)) {
            $services->set(EnumTranslationExtractor::class)
                ->tag('translation.extractor', ['alias' => 'phil_enums']);
        }

        if (class_exists(Command::class)) {
            foreach ([DebugEnumsCommand::class, ExportTypeScriptCommand::class] as $command) {
                $services->set($command)
                    ->args([$config['paths'], service('translator')->nullOnInvalid()])
                    ->tag('console.command');
            }

            $services->set(ExportIcuCommand::class)
                ->args([service('translator')->nullOnInvalid()])
                ->tag('console.command');

            if (interface_exists(EntityManagerInterface::class)) {
                // DoctrineBundle may be installed and not registered: the command then says so.
                $services->set(CheckSetColumnsCommand::class)
                    ->args([service('doctrine')->nullOnInvalid()])
                    ->tag('console.command');
            }
        }

        if (interface_exists(ValueResolverInterface::class)) {
            $services->set(ParsedEnumValueResolver::class)
                ->tag('controller.argument_value_resolver', ['name' => ParsedEnumValueResolver::class]);
        }

        if (interface_exists(ModelDescriberInterface::class)) {
            // Before NelmioApiDoc's own enum describer, which would describe the enums of `phil/enums` without their
            // labels.
            $services->set(NelmioEnumModelDescriber::class)
                ->tag('nelmio_api_doc.model_describer', ['before' => 'nelmio_api_doc.model_describers.enum']);
        }

        if (interface_exists(PropertyMetadataFactoryInterface::class)) {
            // Around API Platform's schema factory, so that it completes the schema that one writes, and within its
            // cache, so that the completed schema is the one kept; removed with the decorated service when API
            // Platform's bundle is not registered.
            $services->set(ApiPlatformEnumPropertyMetadataFactory::class)
                ->decorate('api_platform.metadata.property.metadata_factory', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
                ->tag('container.decoration_order', ['priority' => null, 'around' => 'api_platform.json_schema.metadata.property.metadata_factory.schema', 'within' => 'api_platform.metadata.property.metadata_factory.cached'])
                ->args([service('.inner')]);
        }

        if (class_exists(AbstractMaker::class)) {
            $services->set(MakeEnum::class)
                ->tag('maker.command');
        }

        if (interface_exists(ExpressionFunctionProviderInterface::class)) {
            $services->set(EnumsExpressionProvider::class)
                ->tag('security.expression_language_provider')
                ->tag('routing.expression_language_provider')
                ->tag('validator.expression_language_provider');
        }

        if (interface_exists(NormalizerInterface::class)) {
            // Before Symfony's normalizers of translatable values, enum cases and objects, which would write the
            // structures of `phil/enums` and the cases asked with their labels in their own way.
            $services->set(EnumsNormalizer::class)
                ->args([service('translator')->nullOnInvalid()])
                ->tag('serializer.normalizer', ['before' => ['serializer.normalizer.translatable', 'serializer.normalizer.backed_enum', 'serializer.normalizer.object']]);
        }
    }

    /**
     * Registers the `EnumSet` column types the configuration declares, once per process: DBAL keeps its types in a
     * static registry, which a type name enters only once.
     */
    #[Override]
    public function boot(): void
    {
        if (!class_exists(Type::class)) {
            return;
        }

        // The kernel sets the container before it boots a bundle.
        \assert($this->container instanceof ContainerInterface);

        /** @var array<string, array{enum: class-string<UnitEnum>, storage: string}> $sets */
        $sets = $this->container->getParameter('phil_enums.doctrine.sets');

        foreach ($sets as $name => $set) {
            if (!Type::hasType($name)) {
                Type::addType($name, new EnumSetType($set['enum'], EnumSetStorage::from($set['storage'])));
            }
        }
    }
}
