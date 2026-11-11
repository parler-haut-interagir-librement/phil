<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\TaxonomyBundle\Application\Taxonomy;
use Phil\TaxonomyBundle\Domain\Repository\TaxonomyRepository;
use Phil\TaxonomyBundle\Infrastructure\Doctrine\DoctrineTaxonomyRepository;
use Phil\TaxonomyBundle\Infrastructure\Form\CategoryType;
use Phil\TaxonomyBundle\Infrastructure\Form\TagsType;
use Phil\TaxonomyBundle\PhilTaxonomyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilTaxonomyBundle::class)]
final class PhilTaxonomyBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['fallback_locale' => 'fr']], $container);

        self::assertSame('phil_taxonomy', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineTaxonomyRepository::class, Taxonomy::class, TagsType::class, CategoryType::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineTaxonomyRepository::class)->getArguments());
        self::assertSame(DoctrineTaxonomyRepository::class, (string) $container->getAlias(TaxonomyRepository::class));
        self::assertTrue($container->getAlias(TaxonomyRepository::class)->isPublic());
        self::assertEquals([new Reference(TaxonomyRepository::class), 'fr'], $container->getDefinition(Taxonomy::class)->getArguments());
        self::assertTrue($container->getDefinition(Taxonomy::class)->isPublic());
        self::assertTrue($container->getDefinition(TagsType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(Taxonomy::class)], $container->getDefinition(CategoryType::class)->getArguments());
        self::assertTrue($container->getDefinition(CategoryType::class)->hasTag('form.type'));

        $default = new ContainerBuilder();
        $this->extension()->load([], $default);
        self::assertSame('en', $default->getDefinition(Taxonomy::class)->getArgument(1));
        self::assertCount(2, $default->getDefinition(Taxonomy::class)->getArguments());

        $slugged = new ContainerBuilder();
        $this->extension()->load([['slugger' => 'phil_slug.slugger.tags']], $slugged);
        self::assertEquals([new Reference(TaxonomyRepository::class), 'en', new Reference('phil_slug.slugger.tags')], $slugged->getDefinition(Taxonomy::class)->getArguments());
    }

    public function testTheEntitiesAreMappedWithDoctrine(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        /** @var array{orm: array{mappings: array<string, array<string, mixed>>}} $doctrine */
        $doctrine = $container->getExtensionConfig('doctrine')[0];
        $mapping = $doctrine['orm']['mappings']['PhilTaxonomyBundle'];
        self::assertSame(['attribute', false, 'Phil\TaxonomyBundle\Domain\Model'], [$mapping['type'] ?? null, $mapping['is_bundle'] ?? null, $mapping['prefix'] ?? null]);
        self::assertIsString($mapping['dir'] ?? null);
        self::assertStringEndsWith('/src/Domain/Model', $mapping['dir']);

        $without = new ContainerBuilder();
        $without->registerExtension($extension);

        $extension->prepend($without);
        self::assertFalse($without->hasExtension('doctrine'));
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilTaxonomyBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
