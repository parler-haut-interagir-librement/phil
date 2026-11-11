<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Tests\Unit;

use Phil\Slug\LeadingWordsRemoved;
use Phil\Slug\Slugger;
use Phil\Slug\Sluggers;
use Phil\Slug\TransliteratingSlugger;
use Phil\SlugBundle\Application\Contract\SlugHistory;
use Phil\SlugBundle\Infrastructure\DependencyInjection\SluggerServices;
use Phil\SlugBundle\Infrastructure\Doctrine\DoctrineSlugHistory;
use Phil\SlugBundle\Infrastructure\Doctrine\SlugHistorySchemaListener;
use Phil\SlugBundle\Infrastructure\Twig\SlugExtension;
use Phil\SlugBundle\PhilSlugBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilSlugBundle::class)]
#[CoversClass(SluggerServices::class)]
final class PhilSlugBundleTest extends TestCase
{
    public function testWithoutSluggersThereIsOneDefault(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_slug', $this->extension()->getAlias());
        self::assertSame(['service_container', 'phil_slug.slugger.default', Sluggers::class, SlugExtension::class], array_keys($container->getDefinitions()));
        self::assertSame(TransliteratingSlugger::class, $container->getDefinition('phil_slug.slugger.default')->getClass());
        self::assertSame([null, 255], $container->getDefinition('phil_slug.slugger.default')->getArguments());
        self::assertSame('phil_slug.slugger.default', (string) $container->getAlias(Slugger::class));
        self::assertSame('phil_slug.slugger.default', (string) $container->getAlias(Slugger::class.' $defaultSlugger'));
        self::assertEquals([['default' => new Reference('phil_slug.slugger.default')], 'default'], $container->getDefinition(Sluggers::class)->getArguments());
        self::assertTrue($container->getDefinition(Sluggers::class)->isPublic());
        self::assertEquals([new Reference(Sluggers::class)], $container->getDefinition(SlugExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(SlugExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(SlugExtension::class)->hasTag('twig.runtime'));
        self::assertSame(['phil_slug.slugger.', Sluggers::class, 255], [SluggerServices::ID_PREFIX, SluggerServices::SLUGGERS_ID, SluggerServices::MAX_LENGTH]);
    }

    public function testNamedSluggersAreServicesAutowiredByName(): void
    {
        $container = $this->load(['default' => 'titles', 'sluggers' => [
            'titles' => ['locale' => 'de', 'max_length' => 80],
            'directory_names' => ['leading_words' => ['le', 'la', 'les', 'l', 'the']],
        ]]);

        self::assertSame([TransliteratingSlugger::class, ['de', 80]], [$container->getDefinition('phil_slug.slugger.titles')->getClass(), $container->getDefinition('phil_slug.slugger.titles')->getArguments()]);
        $names = $container->getDefinition('phil_slug.slugger.directory_names');
        self::assertSame(LeadingWordsRemoved::class, $names->getClass());
        $inner = $names->getArgument(0);
        self::assertInstanceOf(Definition::class, $inner);
        self::assertSame([TransliteratingSlugger::class, [null, 255]], [$inner->getClass(), $inner->getArguments()]);
        self::assertSame(['le', 'la', 'les', 'l', 'the'], $names->getArgument(1));
        self::assertSame('phil_slug.slugger.titles', (string) $container->getAlias(Slugger::class));
        self::assertSame('phil_slug.slugger.directory_names', (string) $container->getAlias(Slugger::class.' $directoryNamesSlugger'));
        self::assertSame('phil_slug.slugger.titles', (string) $container->getAlias(Slugger::class.' $titlesSlugger'));
        self::assertEquals([['titles' => new Reference('phil_slug.slugger.titles'), 'directory_names' => new Reference('phil_slug.slugger.directory_names')], 'titles'], $container->getDefinition(Sluggers::class)->getArguments());
    }

    public function testTheShortestSlugIsOneCharacter(): void
    {
        self::assertSame([null, 1], $this->load(['sluggers' => ['default' => ['max_length' => 1]]])->getDefinition('phil_slug.slugger.default')->getArguments());
    }

    public function testTheDefaultNamesTheOnlySluggerWhenNoneAreGiven(): void
    {
        $container = $this->load(['default' => 'main']);

        self::assertEquals([['main' => new Reference('phil_slug.slugger.main')], 'main'], $container->getDefinition(Sluggers::class)->getArguments());
        self::assertSame('phil_slug.slugger.main', (string) $container->getAlias(Slugger::class));
    }

    public function testTheHistoryIsOffByDefault(): void
    {
        $container = $this->load(['history' => ['enabled' => false]]);

        self::assertFalse($container->hasDefinition(PhilSlugBundle::HISTORY_ID));
        self::assertFalse($container->hasAlias(SlugHistory::class));
        self::assertFalse($container->hasDefinition(SlugHistorySchemaListener::class));
    }

    public function testTheHistoryIsWiredOnTheDefaultConnectionAndClock(): void
    {
        $container = $this->load(['history' => true]);
        $history = $container->getDefinition(PhilSlugBundle::HISTORY_ID);

        self::assertSame('phil_slug.history', PhilSlugBundle::HISTORY_ID);
        self::assertSame(DoctrineSlugHistory::class, $history->getClass());
        self::assertEquals([new Reference('doctrine.dbal.default_connection'), new Reference('clock')], $history->getArguments());
        self::assertSame(PhilSlugBundle::HISTORY_ID, (string) $container->getAlias(SlugHistory::class));
        $listener = $container->getDefinition(SlugHistorySchemaListener::class);
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $listener->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $listener->getTag('doctrine.event_listener'));
    }

    public function testTheHistoryTakesItsConnectionAndClock(): void
    {
        $container = $this->load(['history' => ['connection' => 'archive', 'clock' => 'app.clock']]);

        self::assertEquals([new Reference('doctrine.dbal.archive_connection'), new Reference('app.clock')], $container->getDefinition(PhilSlugBundle::HISTORY_ID)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'archive']], $container->getDefinition(SlugHistorySchemaListener::class)->getTag('doctrine.event_listener'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'an unknown default' => [['default' => 'nope', 'sluggers' => ['a' => [], 'b' => []]], 'The default slugger "nope" is not one of the sluggers: a, b.'];
        yield 'a bad name' => [['default' => 'ok', 'sluggers' => ['ok' => [], 'Bad' => [], '1x' => []]], 'these are not: Bad, 1x.'];
        yield 'a number as a name' => [['default' => 'ok', 'sluggers' => ['ok' => [], 12 => []]], 'these are not: 12.'];
        yield 'a new line in a name' => [['default' => 'ok', 'sluggers' => ['ok' => [], "a\n" => []]], 'these are not: a'];
        yield 'too short' => [['sluggers' => ['default' => ['max_length' => 0]]], 'max_length'];
        yield 'too long' => [['sluggers' => ['default' => ['max_length' => 256]]], 'max_length'];
        yield 'an empty default' => [['default' => ''], 'default'];
        yield 'a leading word with a capital' => [['sluggers' => ['default' => ['leading_words' => ['le', 'Le']]]], 'A leading word is one word of lowercase ASCII letters and digits, as slugs spell them; "Le" is not.'];
        yield 'a leading word with an apostrophe' => [['sluggers' => ['default' => ['leading_words' => ["l'"]]]], 'A leading word is one word'];
        yield 'a leading word of two words' => [['sluggers' => ['default' => ['leading_words' => ['de-la']]]], 'A leading word is one word'];
        yield 'an empty history connection' => [['history' => ['connection' => '']], 'connection'];
        yield 'an empty history clock' => [['history' => ['clock' => '']], 'clock'];
        yield 'a leading word that is no string' => [['sluggers' => ['default' => ['leading_words' => [true]]]], 'A leading word is one word'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
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
        $extension = new PhilSlugBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
