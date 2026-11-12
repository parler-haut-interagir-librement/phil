<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\TranslationBundle\Application\Comments;
use Phil\TranslationBundle\Application\Glossaries;
use Phil\TranslationBundle\Application\Translations;
use Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository;
use Phil\TranslationBundle\Domain\Repository\SegmentRepository;
use Phil\TranslationBundle\Domain\Repository\TermRepository;
use Phil\TranslationBundle\Infrastructure\DependencyInjection\TranslationServices;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineCommentRepository;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineMemoryStore;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineSegmentRepository;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineTermRepository;
use Phil\TranslationBundle\Infrastructure\Http\GlossaryController;
use Phil\TranslationBundle\Infrastructure\Http\SameOriginGuard;
use Phil\TranslationBundle\Infrastructure\Http\TranslationController;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemorySegmentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryTermRepository;
use Phil\TranslationBundle\Infrastructure\Twig\CreditsExtension;
use Phil\TranslationBundle\PhilTranslationBundle;
use Phil\TranslationMemory\Glossary\RegularPlurals;
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\MemoryStore;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\Variants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilTranslationBundle::class)]
#[CoversClass(TranslationServices::class)]
final class PhilTranslationBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);
        $entityManager = new Reference('doctrine.orm.default_entity_manager');

        self::assertSame('phil_translation', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineSegmentRepository::class, DoctrineTermRepository::class, DoctrineMemoryStore::class, DoctrineCommentRepository::class, UjoSegmenter::class, Variants::class, RegularPlurals::class, TranslationMemory::class, Glossaries::class, Comments::class, Translations::class, SameOriginGuard::class, TranslationController::class, GlossaryController::class, CreditsExtension::class], array_keys($container->getDefinitions()));
        self::assertEquals([$entityManager], $container->getDefinition(DoctrineCommentRepository::class)->getArguments());
        self::assertEquals([$entityManager], $container->getDefinition(DoctrineSegmentRepository::class)->getArguments());
        self::assertEquals([$entityManager], $container->getDefinition(DoctrineTermRepository::class)->getArguments());
        self::assertEquals([$entityManager, new Reference('clock')], $container->getDefinition(DoctrineMemoryStore::class)->getArguments());

        foreach ([SegmentRepository::class => DoctrineSegmentRepository::class, TermRepository::class => DoctrineTermRepository::class, MemoryStore::class => DoctrineMemoryStore::class, CommentRepository::class => DoctrineCommentRepository::class] as $alias => $service) {
            self::assertSame([$service, true], [(string) $container->getAlias($alias), $container->getAlias($alias)->isPublic()]);
        }

        self::assertEquals([Granularity::Block], $container->getDefinition(UjoSegmenter::class)->getArguments());
        self::assertEquals([['fr-CA', 'en-CA']], $container->getDefinition(Variants::class)->getArguments());
        self::assertEquals([new Reference(MemoryStore::class), 0.7, 5, 50, new Reference(Variants::class)], $container->getDefinition(TranslationMemory::class)->getArguments());
        self::assertEquals([new Reference(TermRepository::class), null], $container->getDefinition(Glossaries::class)->getArguments());
        self::assertEquals([new Reference(CommentRepository::class), new Reference('clock'), null], $container->getDefinition(Comments::class)->getArguments());
        self::assertEquals([new Reference(SegmentRepository::class), new Reference(TranslationMemory::class), new Reference(Glossaries::class), new Reference(UjoSegmenter::class), new Reference('clock'), true, new Reference(Variants::class), null, null, '$policy' => null], $container->getDefinition(Translations::class)->getArguments());
        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(SameOriginGuard::class)->getArguments());
        self::assertEquals([new Reference(Translations::class), new Reference(Comments::class), new Reference('twig'), new Reference('router'), new Reference(SameOriginGuard::class), PhilTranslationBundle::DEFAULT_LAYOUT, null, null], $container->getDefinition(TranslationController::class)->getArguments());
        self::assertEquals([new Reference(Glossaries::class), new Reference('twig'), new Reference('router'), new Reference(SameOriginGuard::class), PhilTranslationBundle::DEFAULT_LAYOUT, null], $container->getDefinition(GlossaryController::class)->getArguments());
        self::assertEquals([new Reference(Translations::class)], $container->getDefinition(CreditsExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(TranslationController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(GlossaryController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(CreditsExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(CreditsExtension::class)->hasTag('twig.runtime'));

        foreach ([TranslationMemory::class, Glossaries::class, Comments::class, Translations::class, TranslationController::class, GlossaryController::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        self::assertFalse($container->getDefinition(UjoSegmenter::class)->isPublic());
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['store' => 'memory', 'granularity' => 'sentence', 'prefill' => false, 'min_score' => 0.8, 'max_suggestions' => 3, 'sources' => 'app.sources', 'policy' => 'app.policy', 'layout' => 'base.html.twig', 'variants' => ['fr-CA', 'pt-BR'], 'translators' => 'app.translators', 'inflector' => RegularPlurals::class, 'machine_translation' => ['enabled' => true, 'translator' => 'app.machine']]);

        self::assertSame(['service_container', InMemorySegmentRepository::class, InMemoryTermRepository::class, InMemoryStore::class, InMemoryCommentRepository::class, UjoSegmenter::class, Variants::class, RegularPlurals::class, TranslationMemory::class, Glossaries::class, Comments::class, Translations::class, SameOriginGuard::class, TranslationController::class, GlossaryController::class, CreditsExtension::class], array_keys($container->getDefinitions()));
        self::assertEquals([['fr-CA', 'pt-BR']], $container->getDefinition(Variants::class)->getArguments());
        self::assertEquals([new Reference(TermRepository::class), new Reference(RegularPlurals::class)], $container->getDefinition(Glossaries::class)->getArguments());
        self::assertEquals([new Reference(CommentRepository::class), new Reference('clock'), new Reference('app.translators')], $container->getDefinition(Comments::class)->getArguments());
        self::assertEquals([new Reference(Variants::class), new Reference('app.translators'), new Reference('app.machine'), '$policy' => new Reference('app.policy')], \array_slice($container->getDefinition(Translations::class)->getArguments(), 6));
        self::assertEquals(['base.html.twig', new Reference('app.policy')], \array_slice($container->getDefinition(GlossaryController::class)->getArguments(), 4));
        self::assertNull($this->load(['machine_translation' => ['translator' => 'app.machine']])->getDefinition(Translations::class)->getArgument(8), 'off unless enabled');

        foreach ([SegmentRepository::class => InMemorySegmentRepository::class, TermRepository::class => InMemoryTermRepository::class, MemoryStore::class => InMemoryStore::class, CommentRepository::class => InMemoryCommentRepository::class] as $alias => $service) {
            self::assertSame([$service, true], [(string) $container->getAlias($alias), $container->getAlias($alias)->isPublic()]);
        }

        self::assertEquals([Granularity::Sentence], $container->getDefinition(UjoSegmenter::class)->getArguments());
        self::assertEquals([new Reference(MemoryStore::class), 0.8, 3, 50, new Reference(Variants::class)], $container->getDefinition(TranslationMemory::class)->getArguments());
        self::assertFalse($container->getDefinition(Translations::class)->getArgument(5));
        self::assertEquals(['base.html.twig', new Reference('app.sources'), new Reference('app.policy')], \array_slice($container->getDefinition(TranslationController::class)->getArguments(), 5));
        self::assertEquals([new Reference('doctrine.orm.translations_entity_manager')], $this->load(['entity_manager' => 'translations'])->getDefinition(DoctrineSegmentRepository::class)->getArguments());
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([['store' => 'redis'], ['entity_manager' => ''], ['granularity' => 'word'], ['min_score' => 0.0], ['min_score' => 1.5], ['max_suggestions' => 0], ['layout' => ''], ['variants' => ['fr']], ['variants' => ['fr_CA']], ['variants' => [42]], ['machine_translation' => ['enabled' => true]]] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_translation', $invalidConfigurationException->getMessage());
            }
        }

        self::assertEquals([new Reference(MemoryStore::class), 0.01, 1, 50, new Reference(Variants::class)], $this->load(['min_score' => 0.01, 'max_suggestions' => 1])->getDefinition(TranslationMemory::class)->getArguments());
        self::assertEquals([new Reference(MemoryStore::class), 1.0, 5, 50, new Reference(Variants::class)], $this->load(['min_score' => 1.0])->getDefinition(TranslationMemory::class)->getArguments());
        self::assertEquals([[]], $this->load(['variants' => []])->getDefinition(Variants::class)->getArguments());
        self::assertEquals([Granularity::Block], $this->load(['store' => 'doctrine', 'granularity' => 'block'])->getDefinition(UjoSegmenter::class)->getArguments());
    }

    public function testTheMappingIsPrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilTranslationBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\TranslationBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
        self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_translation']]], ['asset_mapper' => ['paths' => [PhilTranslationBundle::CONTROLLERS_DIR => '@phil/translation-bundle']]]], $container->getExtensionConfig('framework'));
        self::assertFileExists(PhilTranslationBundle::CONTROLLERS_DIR.'/csrf_controller.js');

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([[], [], []], [$bare->getExtensionConfig('doctrine'), $bare->getExtensionConfig('framework'), $bare->getExtensionConfig('phil_translation')]);
        self::assertFileExists(new PhilTranslationBundle()->getPath().'/templates/translate.html.twig');
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
        $extension = new PhilTranslationBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
