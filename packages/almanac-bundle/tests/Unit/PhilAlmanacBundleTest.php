<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\Almanac\Almanac;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\Rule;
use Phil\Almanac\Rule\RuleParser;
use Phil\AlmanacBundle\Application\AlmanacFactory;
use Phil\AlmanacBundle\Application\ConfiguredDates;
use Phil\AlmanacBundle\Application\Definitions;
use Phil\AlmanacBundle\Application\Places;
use Phil\AlmanacBundle\Domain\Model\StoredDate;
use Phil\AlmanacBundle\Domain\Repository\StoredDateRepository;
use Phil\AlmanacBundle\Infrastructure\Doctrine\DoctrineStoredDateRepository;
use Phil\AlmanacBundle\Infrastructure\Form\StoredDateType;
use Phil\AlmanacBundle\Infrastructure\Twig\AlmanacExtension;
use Phil\AlmanacBundle\Infrastructure\Twig\SkyExtension;
use Phil\AlmanacBundle\PhilAlmanacBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilAlmanacBundle::class)]
#[CoversClass(ConfiguredDates::class)]
final class PhilAlmanacBundleTest extends TestCase
{
    public function testTheServicesWithoutTheDatabase(): void
    {
        $container = $this->load([]);

        self::assertSame(['service_container', Definitions::class, Places::class, AlmanacFactory::class, Almanac::class, StoredDateType::class, AlmanacExtension::class, SkyExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([[]], $container->getDefinition(Places::class)->getArguments());
        self::assertTrue($container->getDefinition(Places::class)->isPublic());
        self::assertEquals([new Reference(Places::class), new Reference(AlmanacFactory::class)], $container->getDefinition(SkyExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(SkyExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(SkyExtension::class)->hasTag('twig.runtime'));
        self::assertEquals([[], null], $container->getDefinition(Definitions::class)->getArguments());
        self::assertSame([ConfiguredDates::class, 'definitions'], $container->getDefinition(Definitions::class)->getFactory());
        self::assertEquals([new Reference(Definitions::class), 'UTC'], $container->getDefinition(AlmanacFactory::class)->getArguments());
        self::assertEquals([new Reference(AlmanacFactory::class), 'almanac'], $container->getDefinition(Almanac::class)->getFactory());
        self::assertFalse($container->getDefinition(Almanac::class)->isShared());
        self::assertTrue($container->getDefinition(Almanac::class)->isPublic());
        self::assertTrue($container->getDefinition(Definitions::class)->isPublic());
        self::assertTrue($container->getDefinition(AlmanacFactory::class)->isPublic());
        self::assertTrue($container->getDefinition(StoredDateType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(AlmanacFactory::class)], $container->getDefinition(AlmanacExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(AlmanacExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(AlmanacExtension::class)->hasTag('twig.runtime'));
        self::assertSame('phil_almanac', new PhilAlmanacBundle()->getContainerExtension()?->getAlias());
    }

    public function testTheConfiguredDates(): void
    {
        $container = $this->load(['timezone' => 'America/Toronto', 'dates' => [
            'oktoberfest' => ['title' => 'Oktoberfest', 'rule' => 'Saturday on or after 09-15', 'until' => 'sunday on or after 10-03', 'tags' => ['fete']],
            'moon' => ['title' => 'Pleine lune', 'rule' => 'full moon', 'all_day' => false],
            'week' => ['title' => 'Semaine', 'rule' => 'monday on or before 02-22', 'days' => 7],
        ]]);

        $dates = $container->getDefinition(Definitions::class)->getArgument(0);
        self::assertIsArray($dates);
        self::assertCount(3, $dates);
        self::assertSame('America/Toronto', $container->getDefinition(AlmanacFactory::class)->getArgument(1));
        /** @var array<string, array{title: string, rule: string, until: ?string, days: int, all_day: bool, tags: list<string>}> $dates */
        $definitions = ConfiguredDates::definitions($dates);
        self::assertSame(
            [['oktoberfest', 'Oktoberfest', 'saturday on or after 09-15', 'sunday on or after 10-03', 1, true, ['fete']], ['moon', 'Pleine lune', 'full moon', null, 1, false, []], ['week', 'Semaine', 'monday on or before 02-22', null, 7, true, []]],
            array_map(static fn (Definition $definition): array => [$definition->key, $definition->title, (string) $definition->rule, $definition->until instanceof Rule ? (string) $definition->until : null, $definition->days, $definition->allDay, $definition->tags], $definitions->all()),
        );
    }

    public function testTheEclipsesAndThePlaces(): void
    {
        $container = $this->load(['timezone' => 'America/Toronto', 'eclipses' => ['enabled' => true, 'lunar_title' => 'Éclipse lunaire'], 'places' => [
            'montreal' => ['latitude' => 45.5017, 'longitude' => -73.5673],
            'paris' => ['latitude' => 48, 'longitude' => 2, 'timezone' => 'Europe/Paris'],
        ], 'dates' => ['solar-eclipse' => ['title' => 'Éclipse de Soleil', 'rule' => 'total solar eclipse', 'all_day' => false]]]);

        /** @var array<string, array{title: string, rule: string, until: ?string, days: int, all_day: bool, tags: list<string>}> $dates */
        $dates = $container->getDefinition(Definitions::class)->getArgument(0);
        self::assertSame(['solar-eclipse', 'lunar-eclipse'], array_keys($dates));
        self::assertEquals(['title' => 'Éclipse de Soleil', 'rule' => 'total solar eclipse', 'until' => null, 'days' => 1, 'all_day' => false, 'tags' => []], $dates['solar-eclipse']);
        self::assertSame(['title' => 'Éclipse lunaire', 'rule' => 'lunar eclipse', 'until' => null, 'days' => 1, 'all_day' => false, 'tags' => ['eclipse']], $dates['lunar-eclipse']);
        self::assertSame([[
            'montreal' => ['latitude' => 45.5017, 'longitude' => -73.5673, 'timezone' => 'America/Toronto'],
            'paris' => ['latitude' => 48.0, 'longitude' => 2.0, 'timezone' => 'Europe/Paris'],
        ]], $container->getDefinition(Places::class)->getArguments());

        self::assertSame([], $this->load(['eclipses' => ['enabled' => false, 'solar_title' => 'x']])->getDefinition(Definitions::class)->getArgument(0));
        self::assertSame(
            ['solar-eclipse' => ['title' => 'Solar eclipse', 'rule' => 'solar eclipse', 'until' => null, 'days' => 1, 'all_day' => false, 'tags' => ['eclipse']], 'lunar-eclipse' => ['title' => 'Lunar eclipse', 'rule' => 'lunar eclipse', 'until' => null, 'days' => 1, 'all_day' => false, 'tags' => ['eclipse']]],
            $this->load(['eclipses' => true])->getDefinition(Definitions::class)->getArgument(0),
        );
    }

    /**
     * The limits of the globe are places, and a place keeps its name as it is written, dashes included.
     */
    public function testThePlacesAtTheLimitsOfTheGlobe(): void
    {
        $container = $this->load(['places' => [
            'north-pole' => ['latitude' => 90, 'longitude' => 180],
            'south-pole' => ['latitude' => -90, 'longitude' => -180],
        ]]);

        self::assertSame([[
            'north-pole' => ['latitude' => 90.0, 'longitude' => 180.0, 'timezone' => 'UTC'],
            'south-pole' => ['latitude' => -90.0, 'longitude' => -180.0, 'timezone' => 'UTC'],
        ]], $container->getDefinition(Places::class)->getArguments());
    }

    public function testTheDatabase(): void
    {
        $container = $this->load(['database' => true], doctrine: true);

        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineStoredDateRepository::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(DoctrineStoredDateRepository::class)->getTag('kernel.reset'));
        self::assertSame(DoctrineStoredDateRepository::class, (string) $container->getAlias(StoredDateRepository::class));
        self::assertTrue($container->getAlias(StoredDateRepository::class)->isPublic());
        self::assertEquals([[], new Reference(DoctrineStoredDateRepository::class)], $container->getDefinition(Definitions::class)->getArguments());
        self::assertFalse($this->load([], doctrine: true)->hasDefinition(DoctrineStoredDateRepository::class));
    }

    /**
     * In a kernel, each extension loads in a container of its own: the prepended build parameter says whether Doctrine
     * is registered.
     */
    public function testTheParameterLeftByThePrepend(): void
    {
        $extension = new PhilAlmanacBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        $with = new ContainerBuilder();
        $with->registerExtension(new DoctrineExtension());
        $with->registerExtension($extension);

        $extension->prepend($with);
        self::assertTrue($with->getParameter('.phil_almanac.doctrine'));

        $loaded = new ContainerBuilder();
        $loaded->setParameter('.phil_almanac.doctrine', true);

        $extension->load([['database' => true]], $loaded);
        self::assertTrue($loaded->hasDefinition(DoctrineStoredDateRepository::class));

        $without = new ContainerBuilder();
        $without->registerExtension($extension);

        $extension->prepend($without);
        self::assertFalse($without->getParameter('.phil_almanac.doctrine'));
        $without->registerExtension(new DoctrineExtension());

        $this->expectException(InvalidConfigurationException::class);
        $extension->load([['database' => true]], $without);
    }

    public function testTheDatabaseNeedsDoctrine(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_almanac.database reads the dates kept by Doctrine: register DoctrineBundle, or set it to false.');

        $this->load(['database' => true]);
    }

    public function testTheMappingIsPrependedToDoctrine(): void
    {
        $extension = new PhilAlmanacBundle()->getContainerExtension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_almanac', ['database' => false]);
        $container->loadFromExtension('phil_almanac', ['database' => '%env(bool:ALMANAC_DATABASE)%']);

        $extension->prepend($container);

        $doctrine = $container->getExtensionConfig('doctrine')[0] ?? null;
        self::assertIsArray($doctrine);
        self::assertIsArray($doctrine['orm']);
        self::assertIsArray($doctrine['orm']['mappings']);
        $mapping = $doctrine['orm']['mappings']['PhilAlmanacBundle'];
        self::assertIsArray($mapping);
        self::assertSame(['attribute', false, 'Phil\AlmanacBundle\Domain\Model'], [$mapping['type'], $mapping['is_bundle'], $mapping['prefix']]);
        self::assertIsString($mapping['dir']);
        self::assertDirectoryExists($mapping['dir']);
        self::assertStringEndsWith('/Domain/Model', $mapping['dir']);

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertFalse($bare->hasExtension('doctrine'));

        foreach ([[], [['timezone' => 'UTC']], [['database' => true], ['database' => false]]] as $configs) {
            $without = new ContainerBuilder();
            $without->registerExtension(new DoctrineExtension());
            $without->registerExtension($extension);

            foreach ($configs as $config) {
                $without->loadFromExtension('phil_almanac', $config);
            }

            $extension->prepend($without);
            self::assertSame([], $without->getExtensionConfig('doctrine'), 'no table for an almanac that keeps no date: '.json_encode($configs, \JSON_THROW_ON_ERROR));
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'a time zone' => [['timezone' => 'Nowhere'], 'The time zone is an IANA identifier: "Nowhere".'];
        yield 'a rule' => [['dates' => ['noel' => ['title' => 'Noël', 'rule' => 'every blue moon']]], 'phil_almanac.dates.noel: "every blue moon" is not a rule the almanac reads.'];
        yield 'an until' => [['dates' => ['noel' => ['title' => 'Noël', 'rule' => '12-25', 'until' => 'never']]], 'phil_almanac.dates.noel: "never" is not a rule the almanac reads.'];
        yield 'a key' => [['dates' => ['Noel' => ['title' => 'Noël', 'rule' => '12-25']]], 'phil_almanac.dates.Noel: "Noel" is not a key'];
        yield 'a day at its time' => [['dates' => ['noel' => ['title' => 'Noël', 'rule' => '12-25', 'all_day' => false]]], 'phil_almanac.dates.noel: Only an instant of the sky'];
        yield 'no title' => [['dates' => ['noel' => ['rule' => '12-25']]], 'The child config "title" under "phil_almanac.dates.noel" must be configured'];
        yield 'no rule' => [['dates' => ['noel' => ['title' => 'Noël']]], 'The child config "rule" under "phil_almanac.dates.noel" must be configured'];
        yield 'no day' => [['dates' => ['noel' => ['title' => 'Noël', 'rule' => '12-25', 'days' => 0]]], 'The value 0 is too small for path "phil_almanac.dates.noel.days". Should be greater than or equal to 1'];
        yield 'too many days' => [['dates' => ['noel' => ['title' => 'Noël', 'rule' => '12-25', 'days' => 367]]], 'The value 367 is too big for path "phil_almanac.dates.noel.days". Should be less than or equal to 366'];
        yield 'a latitude' => [['places' => ['north' => ['latitude' => 90.5, 'longitude' => 0]]], 'The value 90.5 is too big for path "phil_almanac.places.north.latitude"'];
        yield 'a longitude' => [['places' => ['west' => ['latitude' => 0, 'longitude' => -181]]], 'The value -181 is too small for path "phil_almanac.places.west.longitude"'];
        yield 'a latitude south of the pole' => [['places' => ['south' => ['latitude' => -90.5, 'longitude' => 0]]], 'The value -90.5 is too small for path "phil_almanac.places.south.latitude"'];
        yield 'a longitude past the antimeridian' => [['places' => ['east' => ['latitude' => 0, 'longitude' => 180.5]]], 'The value 180.5 is too big for path "phil_almanac.places.east.longitude"'];
        yield 'no latitude' => [['places' => ['here' => ['longitude' => 0]]], 'The child config "latitude" under "phil_almanac.places.here" must be configured'];
        yield "a place's time zone" => [['places' => ['here' => ['latitude' => 0, 'longitude' => 0, 'timezone' => 'Nowhere']]], 'The time zone is an IANA identifier: "Nowhere".'];
        yield 'an empty eclipse title' => [['eclipses' => ['solar_title' => '']], 'The path "phil_almanac.eclipses.solar_title" cannot contain an empty value'];
        yield 'an empty tag' => [['dates' => ['noel' => ['title' => 'Noël', 'rule' => '12-25', 'tags' => ['']]]], 'The path "phil_almanac.dates.noel.tags.0" cannot contain an empty value, but got "".'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testAConfigurationIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    public function testTheLongestDate(): void
    {
        $dates = $this->load(['dates' => ['year' => ['title' => 'A year', 'rule' => '01-01', 'days' => 366]]])->getDefinition(Definitions::class)->getArgument(0);

        self::assertSame(['year' => ['title' => 'A year', 'rule' => '01-01', 'days' => 366, 'until' => null, 'all_day' => true, 'tags' => []]], $dates);
    }

    /**
     * The configured dates read with the stored ones, and a key that is a number.
     */
    public function testConfiguredDatesAndStoredOnes(): void
    {
        $stored = new InMemoryStoredDateRepository();
        $definitions = ConfiguredDates::definitions([2026 => ['title' => 'An year', 'rule' => '01-01', 'until' => null, 'days' => 1, 'all_day' => true, 'tags' => []]], $stored);

        self::assertSame(['2026'], array_map(static fn (Definition $definition): string => $definition->key, $definitions->all()));
        $stored->save(new StoredDate(new Definition('noel', 'Noël', RuleParser::parse('12-25'))));
        self::assertCount(2, $definitions->all());
        ConfiguredDates::check([]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $doctrine = false): ContainerBuilder
    {
        $extension = new PhilAlmanacBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $container = new ContainerBuilder();

        if ($doctrine) {
            $container->registerExtension(new DoctrineExtension());
        }

        $extension->load([$config], $container);

        return $container;
    }
}
