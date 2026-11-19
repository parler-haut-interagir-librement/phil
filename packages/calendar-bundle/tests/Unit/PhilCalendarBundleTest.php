<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use LogicException;
use Phil\Almanac\Almanac;
use Phil\CalendarBundle\Application\AttendancePolicy;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\CalendarChoices;
use Phil\CalendarBundle\Application\EventTagging;
use Phil\CalendarBundle\Application\EventTexts;
use Phil\CalendarBundle\Application\ExpansionBudget;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\PersonalFeeds;
use Phil\CalendarBundle\Application\PrintableCalendar;
use Phil\CalendarBundle\Application\Proposals;
use Phil\CalendarBundle\Application\Venues;
use Phil\CalendarBundle\Application\View\AlmanacImport;
use Phil\CalendarBundle\Application\View\AlmanacItems;
use Phil\CalendarBundle\Application\View\CalendarViews;
use Phil\CalendarBundle\Application\View\EventItems;
use Phil\CalendarBundle\Application\View\HostLabels;
use Phil\CalendarBundle\Application\View\Items;
use Phil\CalendarBundle\Domain\Repository\AttendanceRepository;
use Phil\CalendarBundle\Domain\Repository\CalendarChoiceRepository;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\Repository\PersonalFeedTokenRepository;
use Phil\CalendarBundle\Domain\Repository\VenueRepository;
use Phil\CalendarBundle\Infrastructure\Console\ImportAlmanacCommand;
use Phil\CalendarBundle\Infrastructure\Console\RefreshTagsCommand;
use Phil\CalendarBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineAttendanceRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineCalendarChoiceRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineEventRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrinePersonalFeedTokenRepository;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineVenueRepository;
use Phil\CalendarBundle\Infrastructure\Form\CalendarChoiceType;
use Phil\CalendarBundle\Infrastructure\Form\EventType;
use Phil\CalendarBundle\Infrastructure\Form\VenueType;
use Phil\CalendarBundle\Infrastructure\Http\CalendarFeedController;
use Phil\CalendarBundle\Infrastructure\Http\PersonalFeedController;
use Phil\CalendarBundle\Infrastructure\Privacy\CalendarPrivacy;
use Phil\CalendarBundle\Infrastructure\Taxonomy\TaxonomyEventTagging;
use Phil\CalendarBundle\Infrastructure\Twig\CalendarExtension;
use Phil\CalendarBundle\Infrastructure\Twig\Components\CalendarComponent;
use Phil\CalendarBundle\Infrastructure\Twig\KitComponents;
use Phil\CalendarBundle\PhilCalendarBundle;
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\RecurrenceBundle\PhilRecurrenceBundle;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\TaxonomyBundle\Application\Taxonomy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\AssetMapper\AssetMapperBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilCalendarBundle::class)]
final class PhilCalendarBundleTest extends TestCase
{
    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([['default_timezone' => 'America/Toronto', 'name' => 'Le Club', 'first_weekday' => 7]]);

        self::assertSame('phil_calendar', $this->extension()->getAlias());
        self::assertSame(['service_container', EventType::class, VenueType::class, CalendarChoiceType::class, ExpansionBudget::class, Calendar::class, Venues::class, CalendarFeedController::class, DoctrineEventRepository::class, DoctrineVenueRepository::class, DoctrineAttendanceRepository::class, DoctrineCalendarChoiceRepository::class, DoctrinePersonalFeedTokenRepository::class, TaxonomyEventTagging::class, RefreshTagsCommand::class, EventTexts::class, Attendees::class, CalendarChoices::class, PersonalFeeds::class, PersonalCalendar::class, PersonalFeedController::class, CalendarPrivacy::class, Proposals::class, Items::class, EventItems::class, AlmanacItems::class, AlmanacImport::class, ImportAlmanacCommand::class, CalendarViews::class, PrintableCalendar::class, CalendarComponent::class, KitComponents::class, CalendarExtension::class], array_keys($container->getDefinitions()));
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments(), 'the subdirectory of the Toolkit, read by its pass');
        self::assertSame([], $container->getDefinition(EventTexts::class)->getArguments(), 'no locale declared: every event in its own text');
        self::assertTrue($container->getDefinition(EventTexts::class)->isPublic());
        self::assertEquals([new Reference(DoctrineAttendanceRepository::class), new Reference('clock'), new Reference(AttendancePolicy::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(Attendees::class)->getArguments());
        self::assertEquals([new Reference(Calendar::class), new Reference(DoctrineAttendanceRepository::class), new Reference(DoctrineEventRepository::class), new Reference(CalendarChoices::class)], $container->getDefinition(PersonalCalendar::class)->getArguments());
        self::assertEquals([new Reference(DoctrineCalendarChoiceRepository::class), new Reference('clock')], $container->getDefinition(CalendarChoices::class)->getArguments());
        self::assertEquals([new Reference(DoctrinePersonalFeedTokenRepository::class), new Reference('clock')], $container->getDefinition(PersonalFeeds::class)->getArguments());
        self::assertEquals([new Reference(PersonalFeeds::class), new Reference(PersonalCalendar::class), new Reference('clock'), 'Le Club'], $container->getDefinition(PersonalFeedController::class)->getArguments());
        self::assertTrue($container->getDefinition(PersonalFeedController::class)->hasTag('controller.service_arguments'));
        self::assertEquals([new Reference(CalendarChoices::class), new Reference(PersonalFeeds::class), new Reference(DoctrineAttendanceRepository::class), new Reference(DoctrineEventRepository::class), 'member'], $container->getDefinition(CalendarPrivacy::class)->getArguments());
        self::assertTrue($container->getDefinition(CalendarPrivacy::class)->hasTag('phil_privacy.exporter'));
        self::assertTrue($container->getDefinition(CalendarPrivacy::class)->hasTag('phil_privacy.eraser'));
        self::assertSame('guest', $this->load([['privacy_member_kind' => 'guest']])->getDefinition(CalendarPrivacy::class)->getArgument(4));
        self::assertSame([ExpansionBudget::OCCURRENCES, ExpansionBudget::EXPANSIONS], $container->getDefinition(ExpansionBudget::class)->getArguments());
        self::assertSame([['method' => 'reset']], $container->getDefinition(ExpansionBudget::class)->getTag('kernel.reset'));
        self::assertTrue($container->getDefinition(ExpansionBudget::class)->isPublic());
        self::assertSame([20, 3], $this->load([['budget' => ['occurrences' => 20, 'expansions' => 3]]])->getDefinition(ExpansionBudget::class)->getArguments());
        self::assertTrue($container->getDefinition(CalendarChoiceType::class)->hasTag('form.type'));
        self::assertSame(DoctrineCalendarChoiceRepository::class, (string) $container->getAlias(CalendarChoiceRepository::class));
        self::assertSame(DoctrinePersonalFeedTokenRepository::class, (string) $container->getAlias(PersonalFeedTokenRepository::class));
        self::assertEquals([new Reference(Calendar::class), new Reference(DoctrineEventRepository::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(Proposals::class)->getArguments());
        self::assertEquals([new Reference(Items::class), 'America/Toronto', 7], $container->getDefinition(PrintableCalendar::class)->getArguments());

        foreach ([Attendees::class, PersonalCalendar::class, CalendarChoices::class, PersonalFeeds::class, Proposals::class, PrintableCalendar::class] as $public) {
            self::assertTrue($container->getDefinition($public)->isPublic(), $public);
        }

        self::assertSame(DoctrineAttendanceRepository::class, (string) $container->getAlias(AttendanceRepository::class));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineAttendanceRepository::class)->getArguments());
        self::assertEquals([new TaggedIteratorArgument('phil_calendar.item_source')], $container->getDefinition(Items::class)->getArguments());
        self::assertTrue($container->getDefinition(Items::class)->isPublic());
        self::assertEquals([new Reference(Calendar::class), new Reference(DoctrineEventRepository::class), new Reference(EventTexts::class), new Reference(PersonalCalendar::class)], $container->getDefinition(EventItems::class)->getArguments());
        self::assertSame([['before' => AlmanacItems::class]], $container->getDefinition(EventItems::class)->getTag('phil_calendar.item_source'));
        self::assertEquals([new ServiceClosureArgument(new Reference(Almanac::class))], $container->getDefinition(AlmanacItems::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(AlmanacItems::class)->getTag('phil_calendar.item_source'));
        self::assertEquals([new ServiceClosureArgument(new Reference(Almanac::class)), new Reference(Calendar::class), 'America/Toronto'], $container->getDefinition(AlmanacImport::class)->getArguments());
        self::assertTrue($container->getDefinition(AlmanacImport::class)->isPublic());
        self::assertEquals([new Reference(AlmanacImport::class)], $container->getDefinition(ImportAlmanacCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(ImportAlmanacCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(Items::class), new Reference(DoctrineVenueRepository::class), 'America/Toronto', 7, new Reference(HostLabels::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(CalendarViews::class)->getArguments());
        self::assertTrue($container->getDefinition(CalendarViews::class)->isPublic());
        self::assertEquals([new Reference(CalendarViews::class), new Reference('clock'), new Reference(ExpansionBudget::class)], $container->getDefinition(CalendarComponent::class)->getArguments());
        self::assertTrue($container->getDefinition(CalendarComponent::class)->isAutoconfigured());
        self::assertSame(1, $this->load([])->getDefinition(CalendarViews::class)->getArgument(3));
        self::assertSame(1, $this->load([['first_weekday' => 1]])->getDefinition(CalendarViews::class)->getArgument(3));
        self::assertEquals(['America/Toronto', new Reference(DoctrineVenueRepository::class)], $container->getDefinition(EventType::class)->getArguments());
        self::assertTrue($container->getDefinition(VenueType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(Venues::class)->isAutowired());
        self::assertTrue($container->getDefinition(Venues::class)->isPublic());
        self::assertSame(DoctrineVenueRepository::class, (string) $container->getAlias(VenueRepository::class));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineVenueRepository::class)->getArguments());
        self::assertTrue($container->getDefinition(EventType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(DoctrineEventRepository::class), new Reference(EventTagging::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(ExpansionBudget::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(Calendar::class)->getArguments());
        self::assertSame([['channel' => 'phil_calendar']], $container->getDefinition(Calendar::class)->getTag('monolog.logger'));
        self::assertEquals([new Reference(Taxonomy::class)], $container->getDefinition(TaxonomyEventTagging::class)->getArguments());
        self::assertSame(TaxonomyEventTagging::class, (string) $container->getAlias(EventTagging::class));
        self::assertTrue($container->getAlias(EventTagging::class)->isPublic());
        self::assertEquals([new Reference(Calendar::class)], $container->getDefinition(RefreshTagsCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(RefreshTagsCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(Calendar::class)->isPublic());
        self::assertEquals([new Reference(Calendar::class), new Reference('clock'), 'Le Club'], $container->getDefinition(CalendarFeedController::class)->getArguments());
        self::assertTrue($container->getDefinition(CalendarFeedController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(CalendarFeedController::class)->isPublic());
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineEventRepository::class)->getArguments());
        self::assertSame(DoctrineEventRepository::class, (string) $container->getAlias(EventRepository::class));
        self::assertTrue($container->getAlias(EventRepository::class)->isPublic());
        self::assertEquals([new Reference(Calendar::class), new Reference(NodeCollector::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(EventTexts::class), new Reference(Attendees::class), new Reference(PersonalCalendar::class), new Reference(KitComponents::class), new Reference(CalendarChoices::class)], $container->getDefinition(CalendarExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(CalendarExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(CalendarExtension::class)->hasTag('twig.runtime'));
        self::assertSame('UTC', $this->load([])->getDefinition(EventType::class)->getArgument(0));
        self::assertNull($this->load([])->getDefinition(CalendarFeedController::class)->getArgument(2));
    }

    /**
     * The almanac's dates are a source only where an almanac is registered.
     */
    public function testTheAlmanacIsASourceWhenItIsThere(): void
    {
        $without = $this->load([]);
        $this->builtPass($without)->process($without);
        self::assertSame([false, false, false], [$without->hasDefinition(AlmanacItems::class), $without->hasDefinition(AlmanacImport::class), $without->hasDefinition(ImportAlmanacCommand::class)]);

        $with = $this->load([]);
        $with->register(Almanac::class, Almanac::class);
        $this->builtPass($with)->process($with);
        self::assertSame([true, true, true], [$with->hasDefinition(AlmanacItems::class), $with->hasDefinition(AlmanacImport::class), $with->hasDefinition(ImportAlmanacCommand::class)]);

        $removed = new ContainerBuilder();
        $this->builtPass($removed)->process($removed);
        self::assertFalse($removed->hasDefinition(AlmanacItems::class));
    }

    /**
     * The events' tags are the taxonomy's only where a taxonomy is registered, by a definition or an alias.
     */
    public function testTheTaxonomyHoldsTheTagsWhenItIsThere(): void
    {
        $without = $this->load([]);
        $this->builtPass($without)->process($without);
        self::assertSame([false, false, false], [$without->hasDefinition(TaxonomyEventTagging::class), $without->hasDefinition(RefreshTagsCommand::class), $without->hasAlias(EventTagging::class)]);

        $with = $this->load([]);
        $with->register(Taxonomy::class, Taxonomy::class);
        $this->builtPass($with)->process($with);
        self::assertSame([true, true, true], [$with->hasDefinition(TaxonomyEventTagging::class), $with->hasDefinition(RefreshTagsCommand::class), $with->hasAlias(EventTagging::class)]);

        $aliased = $this->load([]);
        $aliased->setAlias(Taxonomy::class, 'some.taxonomy');
        $this->builtPass($aliased)->process($aliased);
        self::assertTrue($aliased->hasDefinition(TaxonomyEventTagging::class));
    }

    /**
     * The compiler pass the bundle adds when it is built.
     */
    private function builtPass(ContainerBuilder $container): CompilerPassInterface
    {
        $before = $container->getCompilerPassConfig()->getPasses();
        new PhilCalendarBundle()->build($container);
        $added = array_values(array_filter($container->getCompilerPassConfig()->getPasses(), static fn (CompilerPassInterface $pass): bool => !\in_array($pass, $before, true)));
        self::assertCount(2, $added);
        self::assertInstanceOf(KitSubdirectoryPass::class, $added[0]);

        return $added[1];
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function weekdays(): iterable
    {
        yield 'none' => [0];
        yield 'past Sunday' => [8];
    }

    #[DataProvider('weekdays')]
    public function testAWeekStartsOnADay(int $weekday): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('first_weekday');

        $this->load([['first_weekday' => $weekday]]);
    }

    /**
     * Declared locales translate the events, by phil/doctrine-behaviours on the entity manager; a chain that names a
     * locale not served stops the build.
     */
    public function testTheTranslations(): void
    {
        $container = $this->load([['translations' => ['locales' => ['fr_CA' => ['fr'], 'fr' => [], 'en' => []]]]]);

        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition('phil_calendar.translations')->getArguments());
        self::assertSame(Translations::class, $container->getDefinition('phil_calendar.translations')->getClass());
        self::assertSame([DeclaredLocales::class, 'fromArray'], $container->getDefinition('phil_calendar.locales')->getFactory());
        self::assertSame([['fr_CA' => ['fr'], 'fr' => [], 'en' => []]], $container->getDefinition('phil_calendar.locales')->getArguments());
        self::assertEquals([new Reference('phil_calendar.translations'), new Reference('phil_calendar.locales'), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(EventTexts::class)->getArguments());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_calendar.translations.locales:');
        $this->load([['translations' => ['locales' => ['fr_CA' => ['fr']]]]]);
    }

    /**
     * A locale is written with an underscore, a dash is not turned into one, and a refused chain says where it is and why.
     */
    public function testALocaleIsKeptAsWritten(): void
    {
        $container = $this->load([['translations' => ['locales' => ['fr_CA' => ['fr'], 'fr' => [], 'en_US' => []]]]]);
        self::assertSame([['fr_CA' => ['fr'], 'fr' => [], 'en_US' => []]], $container->getDefinition('phil_calendar.locales')->getArguments());

        // A dash is not turned into the underscore a locale is written with.
        foreach ([['fr-CA' => ['fr'], 'fr' => []], ['fr_CA' => ['fr']]] as $locales) {
            try {
                $this->load([['translations' => ['locales' => $locales]]]);
                self::fail('A locale of a dash, or a chain naming a locale not served, is refused.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertMatchesRegularExpression('/^(?:Invalid configuration for path "phil_calendar.translations.locales": )?phil_calendar\.translations\.locales: \S/', $invalidConfigurationException->getMessage());
                self::assertSame(0, $invalidConfigurationException->getCode());
                self::assertInstanceOf(InvalidLocaleDimension::class, $invalidConfigurationException->getPrevious());
            }
        }
    }

    public function testTheBudgetStartsAtOne(): void
    {
        self::assertSame([1, 1], $this->load([['budget' => ['occurrences' => 1, 'expansions' => 1]]])->getDefinition(ExpansionBudget::class)->getArguments());

        foreach (['occurrences', 'expansions'] as $name) {
            try {
                $this->load([['budget' => [$name => 0]]]);
                self::fail('A budget of none is refused.');
            } catch (InvalidConfigurationException $exception) {
                self::assertStringContainsString($name, $exception->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function memberKinds(): iterable
    {
        yield 'with a space inside' => ['ab cd'];
        yield 'with a space before' => [' ab'];
        yield 'with a line after' => ["member\n"];
        yield 'not text' => [5];
        yield 'too long' => [str_repeat('a', 65)];
    }

    #[DataProvider('memberKinds')]
    public function testTheKindOfMemberIsRefusedWhenMalformed(mixed $kind): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The kind of member is 1 to 64 letters');

        $this->load([['privacy_member_kind' => $kind]]);
    }

    public function testTheDefaultTimeZoneIsAnIanaIdentifier(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The default time zone is an IANA identifier');

        $this->load([['default_timezone' => 'Mars/Olympus']]);
    }

    public function testTheEntitiesAreMappedAndTheRecurrenceBundleIsRequired(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $recurrence = new PhilRecurrenceBundle()->getContainerExtension();
        self::assertNotNull($recurrence);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension($recurrence);
        $container->registerExtension($extension);

        $extension->prepend($container);

        /** @var array{orm?: array{mappings?: array<string, array<string, mixed>>}} $doctrine */
        $doctrine = $container->getExtensionConfig('doctrine')[0];
        $mapping = $doctrine['orm']['mappings']['PhilCalendarBundle'] ?? null;
        self::assertIsArray($mapping);
        self::assertSame(['attribute', false, 'Phil\CalendarBundle\Domain\Model'], [$mapping['type'] ?? null, $mapping['is_bundle'] ?? null, $mapping['prefix'] ?? null]);
        self::assertIsString($mapping['dir'] ?? null);
        self::assertStringEndsWith('/src/Domain/Model', $mapping['dir']);

        $withoutDoctrine = new ContainerBuilder();
        $withoutDoctrine->registerExtension($recurrence);
        $withoutDoctrine->registerExtension($extension);

        $extension->prepend($withoutDoctrine);
        self::assertFalse($withoutDoctrine->hasExtension('doctrine'));

        $assets = new ContainerBuilder();
        $assets->registerExtension(new AssetMapperBundle()->getContainerExtension() ?? throw new LogicException('AssetMapper has an extension.'));
        $assets->registerExtension($recurrence);
        $assets->registerExtension($extension);

        $extension->prepend($assets);
        $assetMapper = $assets->getExtensionConfig('asset_mapper')[0] ?? null;
        self::assertIsArray($assetMapper);
        $paths = $assetMapper['paths'] ?? null;
        self::assertIsArray($paths);
        self::assertSame(['@phil/calendar-bundle', 'phil-calendar'], array_values($paths));
        self::assertSame([PhilCalendarBundle::CONTROLLERS_DIR, PhilCalendarBundle::STYLES_DIR], array_keys($paths));
        self::assertFileExists(PhilCalendarBundle::CONTROLLERS_DIR.'/calendar_controller.js');
        self::assertFileExists(PhilCalendarBundle::STYLES_DIR.'/calendar.css');

        // FrameworkBundle without AssetMapperBundle: nothing written under framework.asset_mapper.
        $framework = new ContainerBuilder();
        $framework->registerExtension(new FrameworkExtension());
        $framework->registerExtension($recurrence);
        $framework->registerExtension($extension);

        $extension->prepend($framework);
        self::assertSame([], $framework->getExtensionConfig('framework'));
        self::assertFalse($framework->hasExtension('asset_mapper'));
        self::assertSame([], $withoutDoctrine->getExtensionConfig('framework'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('PhilCalendarBundle repeats its events with PhilRecurrenceBundle, from phil/recurrence-bundle: register it too.');

        $extension->prepend(new ContainerBuilder());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCalendarBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
