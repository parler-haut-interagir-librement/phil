<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\LicensingBundle\Application\ExportGuard;
use Phil\LicensingBundle\Application\LicenseCompliance;
use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Contract\LicenseDeclarationRepository;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
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
use Phil\LicensingBundle\PhilLicensingBundle;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;
use Phil\PagedMediaBundle\Application\Event\UnnamedPrintStarting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilLicensingBundle::class)]
final class PhilLicensingBundleTest extends TestCase
{
    public function testWithoutAStoreOnlyTheCatalogAndItsServices(): void
    {
        $container = $this->load([], []);

        self::assertSame('phil_licensing', $this->extension()->getAlias());
        self::assertSame(['service_container', LicenseCatalog::class, LicenseCompliance::class, LicenseNames::class, LicenseFinder::class, CustomTermsWriter::class, AttributionType::class, LicenseFinderStepType::class, LicenseFinderType::class, AttributionDocument::class, LicenseStructuredData::class], array_keys($container->getDefinitions()));
        self::assertSame([[]], $container->getDefinition(LicenseCatalog::class)->getArguments());
        self::assertEquals([new Reference(LicenseCatalog::class)], $container->getDefinition(LicenseCompliance::class)->getArguments());
        self::assertEquals([new Reference(LicenseCatalog::class), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(LicenseNames::class)->getArguments());
        self::assertEquals([new Reference(LicenseNames::class)], $container->getDefinition(AttributionDocument::class)->getArguments());

        self::assertEquals([new Reference(LicenseCatalog::class)], $container->getDefinition(LicenseFinder::class)->getArguments());
        self::assertEquals([new Reference(LicenseNames::class)], $container->getDefinition(CustomTermsWriter::class)->getArguments());

        foreach ([LicenseCatalog::class, LicenseCompliance::class, LicenseNames::class, LicenseFinder::class, CustomTermsWriter::class, AttributionDocument::class, LicenseStructuredData::class] as $id) {
            self::assertTrue($container->getDefinition($id)->isPublic(), $id);
        }

        self::assertEquals([new Reference(LicenseCatalog::class), new Reference(LicenseNames::class)], $container->getDefinition(AttributionType::class)->getArguments());
        self::assertEquals([new Reference(LicenseFinder::class), new Reference(LicenseNames::class)], $container->getDefinition(LicenseFinderStepType::class)->getArguments());
        self::assertEquals([new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(LicenseFinderType::class)->getArguments());

        foreach ([AttributionType::class, LicenseFinderStepType::class, LicenseFinderType::class] as $id) {
            self::assertSame(['form.type' => [[]]], $container->getDefinition($id)->getTags(), $id);
            self::assertFalse($container->getDefinition($id)->isPublic(), $id);
        }

        self::assertFalse($container->hasDefinition(RequirePrintSubject::class));

        self::assertFalse($container->hasAlias(LicenseDeclarationRepository::class));
    }

    public function testWithDoctrineTwigAndSecurityEverything(): void
    {
        $container = $this->load([], ['DoctrineBundle' => DoctrineBundle::class, 'TwigBundle' => TwigBundle::class, 'SecurityBundle' => SecurityBundle::class]);

        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineLicenseDeclarations::class)->getArguments());
        self::assertFalse($container->getDefinition(DoctrineLicenseDeclarations::class)->isPublic());
        self::assertSame(DoctrineLicenseDeclarations::class, (string) $container->getAlias(LicenseDeclarationRepository::class));
        self::assertTrue($container->getAlias(LicenseDeclarationRepository::class)->isPublic());

        $guard = $container->getDefinition(ExportGuard::class);
        self::assertEquals([new Reference(LicenseDeclarationRepository::class), new Reference(LicenseCompliance::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $guard->getArguments());
        self::assertTrue($guard->isPublic());

        $renderer = $container->getDefinition(AttributionRenderer::class);
        self::assertEquals([new Reference('twig'), new Reference(LicenseDeclarationRepository::class), new Reference(LicenseCatalog::class), AttributionRenderer::TEMPLATE], $renderer->getArguments());
        self::assertTrue($renderer->isPublic());

        $extension = $container->getDefinition(LicensingExtension::class);
        self::assertEquals([new Reference(AttributionRenderer::class), new Reference(LicenseNames::class)], $extension->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $extension->getTags());
        self::assertFalse($extension->isPublic());

        $finder = $container->getDefinition(FinderExtension::class);
        self::assertEquals([new Reference('twig'), new Reference(LicenseCatalog::class), new Reference(LicenseNames::class), new Reference(CustomTermsWriter::class)], $finder->getArguments());
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $finder->getTags());
        self::assertFalse($finder->isPublic());

        $listener = $container->getDefinition(GuardPrintedWork::class);
        self::assertEquals([new Reference(ExportGuard::class), []], $listener->getArguments());
        self::assertSame(['kernel.event_listener' => [['event' => PrintStarting::class]]], $listener->getTags());

        $voter = $container->getDefinition(ExportVoter::class);
        self::assertEquals([new Reference(ExportGuard::class)], $voter->getArguments());
        self::assertSame(['security.voter' => [[]]], $voter->getTags());
        self::assertFalse($voter->isPublic());
    }

    public function testARepositoryOfTheApplicationWithoutTwigNorSecurity(): void
    {
        $container = $this->load(['repository' => 'app.declarations', 'template' => 'licensing.html.twig'], ['DoctrineBundle' => DoctrineBundle::class]);

        self::assertSame('app.declarations', (string) $container->getAlias(LicenseDeclarationRepository::class));
        self::assertFalse($container->hasDefinition(DoctrineLicenseDeclarations::class));
        self::assertTrue($container->hasDefinition(ExportGuard::class));
        self::assertFalse($container->hasDefinition(AttributionRenderer::class));
        self::assertFalse($container->hasDefinition(LicensingExtension::class));
        self::assertFalse($container->hasDefinition(FinderExtension::class));
        self::assertFalse($container->hasDefinition(ExportVoter::class));

        $template = $this->load(['template' => 'licensing.html.twig'], ['DoctrineBundle' => DoctrineBundle::class, 'TwigBundle' => TwigBundle::class]);
        self::assertSame('licensing.html.twig', $template->getDefinition(AttributionRenderer::class)->getArgument(3));
    }

    public function testTheStrictPrintingAndTheUnlicensedSubjects(): void
    {
        $container = $this->load(['printing' => ['strict' => true, 'unlicensed_subjects' => ['invoice', 'ticket']]], ['DoctrineBundle' => DoctrineBundle::class]);

        self::assertSame(['kernel.event_listener' => [['event' => UnnamedPrintStarting::class]]], $container->getDefinition(RequirePrintSubject::class)->getTags());
        self::assertSame([], $container->getDefinition(RequirePrintSubject::class)->getArguments());
        self::assertFalse($container->getDefinition(RequirePrintSubject::class)->isPublic());
        self::assertEquals([new Reference(ExportGuard::class), ['invoice', 'ticket']], $container->getDefinition(GuardPrintedWork::class)->getArguments());

        $withoutStore = $this->load(['printing' => ['strict' => true]], []);
        self::assertTrue($withoutStore->hasDefinition(RequirePrintSubject::class));
        self::assertFalse($withoutStore->hasDefinition(GuardPrintedWork::class));
    }

    public function testAnUnlicensedSubjectIsNamed(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['printing' => ['unlicensed_subjects' => ['']]], []);
    }

    public function testTheLicensesOfTheApplication(): void
    {
        $container = $this->load(['licenses' => [
            'house' => ['name' => 'House license', 'url' => 'https://example.com/license', 'reference' => 'Registry 12', 'spdx' => 'LicenseRef-House', 'requirements' => ['attribution', 'share_alike']],
            '2026' => ['name' => 'Yearly'],
        ]], []);

        $licenses = $container->getDefinition(LicenseCatalog::class)->getArgument(0);
        self::assertIsArray($licenses);
        self::assertCount(2, $licenses);
        self::assertInstanceOf(Definition::class, $licenses[0]);
        self::assertSame(License::class, $licenses[0]->getClass());
        self::assertSame([License::class, 'of'], $licenses[0]->getFactory());
        self::assertSame(['house', 'House license', [Requirement::Attribution, Requirement::ShareAlike], 'https://example.com/license', 'Registry 12', 'LicenseRef-House'], $licenses[0]->getArguments());
        self::assertInstanceOf(Definition::class, $licenses[1]);
        self::assertSame(['2026', 'Yearly', [], null, null, null], $licenses[1]->getArguments());
    }

    public function testALicenseTheCatalogRefusesFailsTheBuild(): void
    {
        $this->expectExceptionObject(InvalidLicensing::because('The license "orc" is in the catalog already.'));

        $this->load(['licenses' => ['orc' => ['name' => 'Another ORC']]], []);
    }

    public function testAMalformedLicenseFailsTheBuild(): void
    {
        $this->expectException(InvalidLicensing::class);

        $this->load(['licenses' => ['house' => ['name' => 'House', 'url' => 'ftp://example.com']]], []);
    }

    public function testAnUnknownRequirementIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['licenses' => ['house' => ['name' => 'House', 'requirements' => ['nothing']]]], []);
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilLicensingBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilLicensingBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\LicensingBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));

        $without = new ContainerBuilder();
        $without->setParameter('kernel.bundles', []);
        $without->registerExtension($this->extensionNamed('doctrine'));
        $this->prepending()->prepend($without);
        self::assertSame([], $without->getExtensionConfig('doctrine'));
    }

    /**
     * @param array<string, mixed>        $config
     * @param array<string, class-string> $bundles
     */
    private function load(array $config, array $bundles): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilLicensingBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function prepending(): PrependExtensionInterface
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        return $extension;
    }

    private function extensionNamed(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
