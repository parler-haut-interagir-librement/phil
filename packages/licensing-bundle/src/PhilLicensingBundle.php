<?php

declare(strict_types=1);

namespace Phil\LicensingBundle;

use Override;
use Phil\LicensingBundle\Application\ExportGuard;
use Phil\LicensingBundle\Application\LicenseCompliance;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Contract\LicenseDeclarationRepository;
use Phil\LicensingBundle\Domain\Finder\LicenseFinder;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Infrastructure\Doctrine\DoctrineLicenseDeclarations;
use Phil\LicensingBundle\Infrastructure\Form\AttributionType;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderStepType;
use Phil\LicensingBundle\Infrastructure\Form\Finder\LicenseFinderType;
use Phil\LicensingBundle\Infrastructure\PagedMedia\GuardPrintedWork;
use Phil\LicensingBundle\Infrastructure\PagedMedia\RequirePrintSubject;
use Phil\LicensingBundle\Infrastructure\Security\ExportVoter;
use Phil\LicensingBundle\Infrastructure\StructuredData\LicenseStructuredData;
use Phil\LicensingBundle\Infrastructure\Translation\CustomTermsWriter;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Phil\LicensingBundle\Infrastructure\Twig\AttributionRenderer;
use Phil\LicensingBundle\Infrastructure\Twig\FinderExtension;
use Phil\LicensingBundle\Infrastructure\Twig\LicensingExtension;
use Phil\LicensingBundle\Infrastructure\Ujo\AttributionDocument;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;
use Phil\PagedMediaBundle\Application\Event\UnnamedPrintStarting;
use Phil\StructuredData\Node;
use Phil\Ujo\Document;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Validator\Constraint;

/**
 * Licenses of works and digital products: a catalog of licenses with what each requires, a declaration attached to any
 * subject with the attribution of each resource, the license and attribution page as HTML, UJO (Universal JSON Output)
 * or JSON-LD, and a compliance check with an export guard that refuses a work without a license.
 *
 * The declarations are kept with Doctrine when DoctrineBundle is registered, or by the repository the application
 * names. The page needs TwigBundle; the UJO document `phil/ujo`; the JSON-LD `phil/structured-data`; the voter
 * SecurityBundle. With `phil/paged-media-bundle`, the guard runs before each print that names its work, and the strict
 * printing refuses a print that names none. With `symfony/form` and `symfony/validator`, the attribution form and the
 * license finder in steps; the finder's decision tree itself is pure PHP.
 *
 * @phpstan-type LicenseConfig array{name: string, url: string|null, reference: string|null, spdx: string|null, requirements: list<string>}
 * @phpstan-type Config array{repository: string|null, template: string, printing: array{strict: bool, unlicensed_subjects: list<string>}, licenses: array<array-key, LicenseConfig>}
 */
final class PhilLicensingBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_licensing';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('repository')
                    ->info('Service id of a LicenseDeclarationRepository; the Doctrine one when DoctrineBundle is registered.')
                    ->defaultNull()
                ->end()
                ->scalarNode('template')
                    ->info('The Twig template of the license and attribution page, a fragment.')
                    ->defaultValue(AttributionRenderer::TEMPLATE)
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('printing')
                    ->info('The guard of the prints of phil/paged-media-bundle.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('strict')
                            ->info('Refuses a print that names no work with a PrintSubject, whose licensing cannot be checked.')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('unlicensed_subjects')
                            ->info('The subject types printed without a license: invoices, tickets, receipts.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('licenses')
                    ->info('The licenses of the application, after the built-in ones, by code.')
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('url')->defaultNull()->end()
                            ->scalarNode('reference')->defaultNull()->end()
                            ->scalarNode('spdx')->defaultNull()->end()
                            ->arrayNode('requirements')
                                ->enumPrototype()->values(array_map(static fn (Requirement $requirement): string => $requirement->value, Requirement::cases()))->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilLicensingBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\LicensingBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        $licenses = [];
        $definitions = [];

        foreach ($config['licenses'] as $code => $license) {
            $arguments = [(string) $code, $license['name'], array_map(Requirement::from(...), $license['requirements']), $license['url'], $license['reference'], $license['spdx']];
            $licenses[] = License::of(...$arguments);
            $definitions[] = new Definition(License::class, $arguments)->setFactory([License::class, 'of']);
        }

        // Built now, so that a license the catalog refuses — a malformed code or address, a built-in code — fails the
        // container build rather than the first request.
        new LicenseCatalog($licenses);

        $builder->register(LicenseCatalog::class, LicenseCatalog::class)->setArguments([$definitions])->setPublic(true);
        $builder->register(LicenseCompliance::class, LicenseCompliance::class)->setArguments([new Reference(LicenseCatalog::class)])->setPublic(true);
        $builder->register(LicenseNames::class, LicenseNames::class)
            ->setArguments([new Reference(LicenseCatalog::class), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->setPublic(true);

        $builder->register(LicenseFinder::class, LicenseFinder::class)->setArguments([new Reference(LicenseCatalog::class)])->setPublic(true);
        $builder->register(CustomTermsWriter::class, CustomTermsWriter::class)->setArguments([new Reference(LicenseNames::class)])->setPublic(true);
        $this->registerForms($builder);

        if ($this->hasBundle($builder, 'TwigBundle')) {
            $builder->register(FinderExtension::class, FinderExtension::class)
                ->setArguments([new Reference('twig'), new Reference(LicenseCatalog::class), new Reference(LicenseNames::class), new Reference(CustomTermsWriter::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }

        if ($config['printing']['strict'] && class_exists(UnnamedPrintStarting::class)) {
            $builder->register(RequirePrintSubject::class, RequirePrintSubject::class)
                ->addTag('kernel.event_listener', ['event' => UnnamedPrintStarting::class]);
        }

        if (class_exists(Document::class)) {
            $builder->register(AttributionDocument::class, AttributionDocument::class)->setArguments([new Reference(LicenseNames::class)])->setPublic(true);
        }

        if (class_exists(Node::class)) {
            $builder->register(LicenseStructuredData::class, LicenseStructuredData::class)->setPublic(true);
        }

        $repository = $config['repository'];

        if (null === $repository && $this->hasBundle($builder, 'DoctrineBundle')) {
            $builder->register(DoctrineLicenseDeclarations::class, DoctrineLicenseDeclarations::class)->setArguments([new Reference('doctrine.orm.entity_manager')]);
            $repository = DoctrineLicenseDeclarations::class;
        }

        if (null === $repository) {
            return;
        }

        $builder->setAlias(LicenseDeclarationRepository::class, $repository)->setPublic(true);
        $builder->register(ExportGuard::class, ExportGuard::class)
            ->setArguments([new Reference(LicenseDeclarationRepository::class), new Reference(LicenseCompliance::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->setPublic(true);

        if (class_exists(PrintStarting::class)) {
            $builder->register(GuardPrintedWork::class, GuardPrintedWork::class)
                ->setArguments([new Reference(ExportGuard::class), $config['printing']['unlicensed_subjects']])
                ->addTag('kernel.event_listener', ['event' => PrintStarting::class]);
        }

        if ($this->hasBundle($builder, 'TwigBundle')) {
            $builder->register(AttributionRenderer::class, AttributionRenderer::class)
                ->setArguments([new Reference('twig'), new Reference(LicenseDeclarationRepository::class), new Reference(LicenseCatalog::class), $config['template']])
                ->setPublic(true);
            $builder->register(LicensingExtension::class, LicensingExtension::class)
                ->setArguments([new Reference(AttributionRenderer::class), new Reference(LicenseNames::class)])
                ->addTag('twig.attribute_extension')
                ->addTag('twig.runtime');
        }

        if (class_exists(Voter::class) && $this->hasBundle($builder, 'SecurityBundle')) {
            $builder->register(ExportVoter::class, ExportVoter::class)->setArguments([new Reference(ExportGuard::class)])->addTag('security.voter');
        }
    }

    /**
     * The attribution form and the license finder in steps, with `symfony/form` and `symfony/validator`.
     */
    private function registerForms(ContainerBuilder $builder): void
    {
        if (!class_exists(AbstractType::class) || !class_exists(Constraint::class)) {
            return;
        }

        $builder->register(AttributionType::class, AttributionType::class)
            ->setArguments([new Reference(LicenseCatalog::class), new Reference(LicenseNames::class)])
            ->addTag('form.type');
        $builder->register(LicenseFinderStepType::class, LicenseFinderStepType::class)
            ->setArguments([new Reference(LicenseFinder::class), new Reference(LicenseNames::class)])
            ->addTag('form.type');
        $builder->register(LicenseFinderType::class, LicenseFinderType::class)
            ->setArguments([new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->addTag('form.type');
    }

    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of whatever
        // application analyses the bundle, not from the kernel that loads it.
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
