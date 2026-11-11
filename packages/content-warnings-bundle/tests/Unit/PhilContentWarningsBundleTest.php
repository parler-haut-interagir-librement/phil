<?php

declare(strict_types=1);

namespace Phil\ContentWarningsBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\ContentWarnings\Catalogue;
use Phil\ContentWarnings\Display;
use Phil\ContentWarnings\Exception\InvalidCatalogue;
use Phil\ContentWarningsBundle\Application\CatalogueFactory;
use Phil\ContentWarningsBundle\Application\CatalogueLabels;
use Phil\ContentWarningsBundle\Application\Contract\WarningPreferencesStore;
use Phil\ContentWarningsBundle\Application\WarningScreen;
use Phil\ContentWarningsBundle\Infrastructure\Doctrine\ContentWarningsColumnType;
use Phil\ContentWarningsBundle\Infrastructure\Form\ContentWarningsType;
use Phil\ContentWarningsBundle\Infrastructure\Form\WarningPreferencesType;
use Phil\ContentWarningsBundle\Infrastructure\Memory\InMemoryWarningPreferences;
use Phil\ContentWarningsBundle\Infrastructure\Session\SessionWarningPreferences;
use Phil\ContentWarningsBundle\Infrastructure\Twig\ContentWarningsExtension;
use Phil\ContentWarningsBundle\PhilContentWarningsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilContentWarningsBundle::class)]
final class PhilContentWarningsBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilContentWarnings(): void
    {
        self::assertSame('phil_content_warnings', $this->extension()->getAlias());
        self::assertSame('@PhilContentWarnings/fold.html.twig', PhilContentWarningsBundle::FOLD_TEMPLATE);
        self::assertSame('@PhilContentWarnings/form/theme.html.twig', PhilContentWarningsBundle::FORM_THEME);
    }

    public function testTheServicesAreRegisteredWithTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([true, [], []], $container->getDefinition(CatalogueFactory::class)->getArguments());
        self::assertEquals([new Reference(CatalogueFactory::class), 'create'], $container->getDefinition(Catalogue::class)->getFactory());
        self::assertEquals([new Reference(Catalogue::class), ['en']], $container->getDefinition(CatalogueLabels::class)->getArguments());
        self::assertSame([Display::Fold], $container->getDefinition(InMemoryWarningPreferences::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryWarningPreferences::class)->getTag('kernel.reset'));
        self::assertEquals([new Reference('request_stack'), Display::Fold], $container->getDefinition(SessionWarningPreferences::class)->getArguments());
        self::assertSame(SessionWarningPreferences::class, (string) $container->getAlias(WarningPreferencesStore::class));
        self::assertEquals([new Reference(Catalogue::class), new Reference(WarningPreferencesStore::class)], $container->getDefinition(WarningScreen::class)->getArguments());
        self::assertEquals([new Reference(CatalogueLabels::class)], $container->getDefinition(ContentWarningsType::class)->getArguments());
        self::assertTrue($container->getDefinition(ContentWarningsType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(CatalogueLabels::class), Display::Fold], $container->getDefinition(WarningPreferencesType::class)->getArguments());
        self::assertTrue($container->getDefinition(WarningPreferencesType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(WarningScreen::class), new Reference(CatalogueLabels::class)], $container->getDefinition(ContentWarningsExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(ContentWarningsExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(ContentWarningsExtension::class)->hasTag('twig.runtime'));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('fixtures', __DIR__.'/Fixtures');
        $this->extension()->load([[
            'base_catalogue' => false,
            'catalogue_files' => ['%fixtures%/site.json'],
            'catalogue' => ['themes' => ['queues' => ['category' => 'venues', 'label' => ['en' => 'Queues']]]],
            'default_display' => 'hide',
            'fallback_locales' => ['fr', 'en'],
            'preferences_store' => 'app.preferences',
        ]], $container);

        self::assertSame([false, [__DIR__.'/Fixtures/site.json'], ['themes' => ['queues' => ['category' => 'venues', 'label' => ['en' => 'Queues']]]]], $container->getDefinition(CatalogueFactory::class)->getArguments());
        self::assertSame(['fr', 'en'], $container->getDefinition(CatalogueLabels::class)->getArgument(1));
        self::assertSame([Display::Hide], $container->getDefinition(InMemoryWarningPreferences::class)->getArguments());
        self::assertSame(Display::Hide, $container->getDefinition(WarningPreferencesType::class)->getArgument(1));
        self::assertSame('app.preferences', (string) $container->getAlias(WarningPreferencesStore::class));
    }

    public function testABrokenCatalogueStopsTheBuild(): void
    {
        $this->expectException(InvalidCatalogue::class);
        $this->expectExceptionMessageIsOrContains('the theme "crowds" to remove is not in the catalogue');

        $this->load(['catalogue' => ['remove' => ['crowds']]]);
    }

    public function testTheCatalogueIsAnArray(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The catalogue is a definition array');

        $this->load(['catalogue' => 'gore']);
    }

    public function testTheDefaultDisplayIsADisplay(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['default_display' => 'blur']);
    }

    public function testTheColumnTypeAndTheFormThemeArePrepended(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension(new TwigExtension());

        $extension = $this->extension();
        $container->registerExtension($extension);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['dbal' => ['types' => [ContentWarningsColumnType::NAME => ContentWarningsColumnType::class]]]], $container->getExtensionConfig('doctrine'));
        self::assertSame([['form_themes' => [PhilContentWarningsBundle::FORM_THEME]]], $container->getExtensionConfig('twig'));

        $alone = new ContainerBuilder();
        $alone->registerExtension($extension);

        $extension->prepend($alone);
        self::assertFalse($alone->hasExtension('doctrine'));
        self::assertFalse($alone->hasExtension('twig'));
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
        $extension = new PhilContentWarningsBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
