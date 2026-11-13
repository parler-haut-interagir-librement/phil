<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Tests\Unit;

use Phil\Epub\EpubWriter;
use Phil\EpubBundle\Application\AsBookSource;
use Phil\EpubBundle\Application\BookSources;
use Phil\EpubBundle\Application\Epubs;
use Phil\EpubBundle\Application\Message\WriteEpub;
use Phil\EpubBundle\Application\Port\EpubStorage;
use Phil\EpubBundle\Infrastructure\Console\WriteEpubCommand;
use Phil\EpubBundle\Infrastructure\DependencyInjection\EpubServices;
use Phil\EpubBundle\Infrastructure\DependencyInjection\RequiredServicesPass;
use Phil\EpubBundle\Infrastructure\Fonts\EpubFonts;
use Phil\EpubBundle\Infrastructure\Media\MediaAssets;
use Phil\EpubBundle\Infrastructure\Media\MediaLibraryStorage;
use Phil\EpubBundle\Infrastructure\Messenger\EpubHandler;
use Phil\EpubBundle\Infrastructure\Storage\DirectoryStorage;
use Phil\EpubBundle\PhilEpubBundle;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilEpubBundle::class)]
#[CoversClass(EpubServices::class)]
#[CoversClass(RequiredServicesPass::class)]
final class PhilEpubBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([null], $container->getDefinition(EpubWriter::class)->getArguments());
        self::assertEquals([new TaggedIteratorArgument('phil_epub.source', 'name')], $container->getDefinition(BookSources::class)->getArguments());
        self::assertSame(['%kernel.project_dir%/var/epub'], $container->getDefinition(DirectoryStorage::class)->getArguments());
        self::assertFalse($container->hasDefinition(MediaLibraryStorage::class));
        self::assertSame(DirectoryStorage::class, (string) $container->getAlias(EpubStorage::class));
        self::assertTrue($container->getAlias(EpubStorage::class)->isPublic());
        self::assertEquals([new Reference(EpubStorage::class), new Reference(BookSources::class), new Reference(EpubWriter::class)], $container->getDefinition(Epubs::class)->getArguments());
        self::assertEquals([new Reference(Epubs::class)], $container->getDefinition(WriteEpubCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(WriteEpubCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(Epubs::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(EpubHandler::class)->getArguments());
        self::assertSame([['handles' => WriteEpub::class]], $container->getDefinition(EpubHandler::class)->getTag('messenger.message_handler'));
        self::assertEquals([new Reference(FontCatalogue::class), true, 'fonts', 'css/fonts.css'], $container->getDefinition(EpubFonts::class)->getArguments());
        self::assertEquals([new Reference(MediaLibrary::class)], $container->getDefinition(MediaAssets::class)->getArguments());

        foreach ([Epubs::class, EpubFonts::class, MediaAssets::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        self::assertSame('phil_epub.source', EpubServices::SOURCE_TAG);
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'temporary_directory' => '/srv/tmp',
            'storage' => ['type' => 'media', 'directory' => '/srv/books', 'media_collection' => 'books'],
            'fonts' => ['obfuscate' => false, 'directory' => 'type', 'stylesheet' => 'styles/fonts.css'],
        ]);

        self::assertSame(['/srv/tmp'], $container->getDefinition(EpubWriter::class)->getArguments());
        self::assertSame(['/srv/books'], $container->getDefinition(DirectoryStorage::class)->getArguments());
        self::assertEquals([new Reference(MediaLibrary::class), new Reference(MediaRepository::class), 'books', '/srv/tmp'], $container->getDefinition(MediaLibraryStorage::class)->getArguments());
        self::assertSame(MediaLibraryStorage::class, (string) $container->getAlias(EpubStorage::class));
        self::assertSame([false, 'type', 'styles/fonts.css'], \array_slice($container->getDefinition(EpubFonts::class)->getArguments(), 1));
    }

    public function testADirectoryStorageNamedSo(): void
    {
        self::assertSame(DirectoryStorage::class, (string) $this->load(['storage' => ['type' => 'directory']])->getAlias(EpubStorage::class));
    }

    public function testRefusesAnUnknownStorage(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['storage' => ['type' => 's3']]);
    }

    public function testRefusesAnEmptyFontDirectory(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['fonts' => ['directory' => '']]);
    }

    public function testSourcesByAttributeAndTheIntegrationsNeedTheirBundles(): void
    {
        $container = new ContainerBuilder();
        new PhilEpubBundle()->build($container);
        $definition = new ChildDefinition('');
        $container->getAttributeAutoconfigurators()[AsBookSource::class][0]($definition, new AsBookSource('story'), new ReflectionClass(self::class));
        self::assertSame([['name' => 'story']], $definition->getTag('phil_epub.source'));

        $passes = array_values(array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof RequiredServicesPass));
        self::assertCount(1, $passes);

        $without = $this->load([]);
        $passes[0]->process($without);
        self::assertFalse($without->hasDefinition(EpubFonts::class));
        self::assertFalse($without->hasDefinition(MediaAssets::class));

        $with = $this->load([]);
        $with->register(FontCatalogue::class, FontCatalogue::class);
        $with->register(MediaLibrary::class, MediaLibrary::class);
        $passes[0]->process($with);
        self::assertTrue($with->hasDefinition(EpubFonts::class));
        self::assertTrue($with->hasDefinition(MediaAssets::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilEpubBundle()->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('phil_epub', $extension->getAlias());
        $extension->load([$config], $container);

        return $container;
    }
}
