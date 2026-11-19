<?php

declare(strict_types=1);

namespace Phil\CalendarBundle;

use DateTimeZone;
use LogicException;
use Override;
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
use Phil\DoctrineBehaviours\Translations;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\PagedMedia\Template\Calendar as CalendarTemplate;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\StructuredData\Contract\NodeCollector;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Twig\Attribute\AsTwigFunction;

/**
 * A calendar: events and venues in Doctrine, their forms, the iCalendar feed of `config/routes.php`, the views laid
 * out in PHP and the component that draws them on a page, and the calendar in Twig; events in the locales declared,
 * their attendees, a member's own calendar — the sources they follow and hide, their feed at a secret address —, the
 * proposals of members, and the calendar printed. Every expansion of a request shares one `ExpansionBudget`.
 *
 * An event repeats by `phil/recurrence-bundle`'s rules, its column type and its form field: register
 * `PhilRecurrenceBundle` too.
 */
final class PhilCalendarBundle extends AbstractBundle
{
    /** The tag of the services a view's items come from. */
    public const string ITEM_SOURCE = 'phil_calendar.item_source';

    /** The Stimulus controller, under the name Symfony UX gives the controllers of a package. */
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    private const string EXPORTER_TAG = 'phil_privacy.exporter';

    private const string ERASER_TAG = 'phil_privacy.eraser';

    #[Override]
    protected string $extensionAlias = 'phil_calendar';

    /**
     * The almanac's dates join the events, and can be made events, when `phil/almanac-bundle` registers its almanac;
     * the events' tags are those of `phil/taxonomy-bundle` when it registers its taxonomy.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if (!$container->hasDefinition(AlmanacItems::ALMANAC) && !$container->hasAlias(AlmanacItems::ALMANAC)) {
                    foreach ([AlmanacItems::class, AlmanacImport::class, ImportAlmanacCommand::class] as $id) {
                        $container->removeDefinition($id);
                    }
                }

                if (!$container->hasDefinition(TaxonomyEventTagging::TAXONOMY) && !$container->hasAlias(TaxonomyEventTagging::TAXONOMY)) {
                    $container->removeDefinition(TaxonomyEventTagging::class);
                    $container->removeDefinition(RefreshTagsCommand::class);
                    $container->removeAlias(EventTagging::class);
                }
            }
        });
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_timezone')
                    ->info('The time zone of a new event, an IANA identifier: "America/Toronto".')
                    ->defaultValue('UTC')
                    ->validate()
                        ->ifTrue(static fn (mixed $timezone): bool => !\in_array($timezone, DateTimeZone::listIdentifiers(), true))
                        ->thenInvalid('The default time zone is an IANA identifier: %s.')
                    ->end()
                ->end()
                ->scalarNode('name')
                    ->info("The calendar's name in its iCalendar feed.")
                    ->defaultNull()
                ->end()
                ->scalarNode('kit_subdirectory')
                    ->info('Where the recipes of the PHIL UX kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')
                    ->defaultNull()
                ->end()
                ->integerNode('first_weekday')
                    ->info('The day weeks start on in the views, ISO 8601: 1 for Monday, 7 for Sunday.')
                    ->defaultValue(1)
                    ->min(1)
                    ->max(7)
                ->end()
                ->arrayNode('budget')
                    ->info("What one request unrolls at most, its views, feeds, members' calendars and printed months together.")
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('occurrences')
                            ->info('The occurrences unrolled at most.')
                            ->defaultValue(ExpansionBudget::OCCURRENCES)
                            ->min(1)
                            ->max(ExpansionBudget::CEILING)
                        ->end()
                        ->integerNode('expansions')
                            ->info('The repeating events read at most, each walked by its rule whatever it gives.')
                            ->defaultValue(ExpansionBudget::EXPANSIONS)
                            ->min(1)
                            ->max(ExpansionBudget::CEILING)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('privacy_member_kind')
                    ->info('The kind of member a data subject of phil/privacy-bundle is: its identifier is their reference.')
                    ->defaultValue('member')
                    ->validate()
                        ->ifTrue(static fn (mixed $kind): bool => !\is_string($kind) || 1 !== preg_match('/^[\w.:-]{1,64}$/D', $kind))
                        ->thenInvalid('The kind of member is 1 to 64 letters, digits, "_", ".", ":" or "-": %s.')
                    ->end()
                ->end()
                ->arrayNode('translations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('locales')
                            ->info('The locales the events are shown in, each with the locales tried after it: {fr_CA: [fr], fr: [en], en: []}. None: every event in its own text.')
                            ->useAttributeAsKey('locale')
                            ->normalizeKeys(false)
                            ->arrayPrototype()->scalarPrototype()->cannotBeEmpty()->end()->end()
                            ->defaultValue([])
                            ->validate()
                                ->always(static function (array $locales): array {
                                    try {
                                        DeclaredLocales::fromArray($locales);
                                    } catch (InvalidLocaleDimension $invalidLocaleDimension) {
                                        throw new InvalidConfigurationException('phil_calendar.translations.locales: '.$invalidLocaleDimension->getMessage(), 0, $invalidLocaleDimension);
                                    }

                                    return $locales;
                                })
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_recurrence')) {
            throw new LogicException('PhilCalendarBundle repeats its events with PhilRecurrenceBundle, from phil/recurrence-bundle: register it too.');
        }

        // The controller and the stylesheet, for AssetMapper to serve, when its bundle is registered.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [
                self::CONTROLLERS_DIR => '@phil/calendar-bundle',
                self::STYLES_DIR => 'phil-calendar',
            ]]);
        }

        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCalendarBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\CalendarBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->set(EventType::class)->args([$config['default_timezone'], new Reference(DoctrineVenueRepository::class)])->tag('form.type');
        $services->set(VenueType::class)->tag('form.type');
        $services->set(CalendarChoiceType::class)->tag('form.type');

        /** @var array{occurrences: int, expansions: int} $budget */
        $budget = $config['budget'];
        // One budget for every expansion of a request, back to nothing between the requests of a worker.
        $services->set(ExpansionBudget::class)
            ->args([$budget['occurrences'], $budget['expansions']])
            ->tag('kernel.reset', ['method' => 'reset'])
            ->public();
        $services->set(Calendar::class)
            ->args([
                new Reference(DoctrineEventRepository::class),
                new Reference(EventTagging::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(ExpansionBudget::class),
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag('monolog.logger', ['channel' => 'phil_calendar'])
            ->public();
        $services->set(Venues::class)->autowire()->public();
        $services->set(CalendarFeedController::class)
            ->args([new Reference(Calendar::class), new Reference('clock'), $config['name']])
            ->tag('controller.service_arguments')
            ->public();

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineEventRepository::class, DoctrineVenueRepository::class, DoctrineAttendanceRepository::class, DoctrineCalendarChoiceRepository::class, DoctrinePersonalFeedTokenRepository::class] as $adapter) {
            $services->set($adapter)->args([new Reference('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }

        if (class_exists(TaxonomyEventTagging::TAXONOMY)) {
            $services->set(TaxonomyEventTagging::class)->args([new Reference(TaxonomyEventTagging::TAXONOMY)]);
            $services->alias(EventTagging::class, TaxonomyEventTagging::class)->public();

            if (class_exists(Command::class)) {
                $services->set(RefreshTagsCommand::class)->args([new Reference(Calendar::class)])->tag('console.command');
            }
        }

        /** @var array{locales: array<string, list<string>>} $translations */
        $translations = $config['translations'];
        $locales = $translations['locales'];

        if ([] === $locales) {
            $services->set(EventTexts::class)->public();
        } else {
            $services->set('phil_calendar.translations', Translations::class)->args([new Reference('doctrine.orm.entity_manager')]);
            $services->set('phil_calendar.locales', DeclaredLocales::class)->factory([DeclaredLocales::class, 'fromArray'])->args([$locales]);
            $services->set(EventTexts::class)
                ->args([new Reference('phil_calendar.translations'), new Reference('phil_calendar.locales'), new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
                ->public();
        }

        $services->set(Attendees::class)
            ->args([new Reference(DoctrineAttendanceRepository::class), new Reference('clock'), new Reference(AttendancePolicy::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->public();
        $services->set(CalendarChoices::class)->args([new Reference(DoctrineCalendarChoiceRepository::class), new Reference('clock')])->public();
        $services->set(PersonalFeeds::class)->args([new Reference(DoctrinePersonalFeedTokenRepository::class), new Reference('clock')])->public();
        $services->set(PersonalCalendar::class)
            ->args([new Reference(Calendar::class), new Reference(DoctrineAttendanceRepository::class), new Reference(DoctrineEventRepository::class), new Reference(CalendarChoices::class)])
            ->public();
        $services->set(PersonalFeedController::class)
            ->args([new Reference(PersonalFeeds::class), new Reference(PersonalCalendar::class), new Reference('clock'), $config['name']])
            ->tag('controller.service_arguments')
            ->public();

        if (interface_exists(PersonalDataEraser::class)) {
            $services->set(CalendarPrivacy::class)
                ->args([new Reference(CalendarChoices::class), new Reference(PersonalFeeds::class), new Reference(DoctrineAttendanceRepository::class), new Reference(DoctrineEventRepository::class), $config['privacy_member_kind']])
                ->tag(self::EXPORTER_TAG)
                ->tag(self::ERASER_TAG);
        }

        $services->set(Proposals::class)
            ->args([new Reference(Calendar::class), new Reference(DoctrineEventRepository::class), new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->public();

        $services->set(Items::class)->args([new TaggedIteratorArgument(self::ITEM_SOURCE)])->public();
        // Before the almanac's days: of two items that start together with the same title, the calendar's event
        // comes first.
        $services->set(EventItems::class)->args([new Reference(Calendar::class), new Reference(DoctrineEventRepository::class), new Reference(EventTexts::class), new Reference(PersonalCalendar::class)])->tag(self::ITEM_SOURCE, ['before' => AlmanacItems::class]);

        if (class_exists(AlmanacItems::ALMANAC)) {
            $services->set(AlmanacItems::class)->args([new ServiceClosureArgument(new Reference(AlmanacItems::ALMANAC))])->tag(self::ITEM_SOURCE);
            $services->set(AlmanacImport::class)->args([new ServiceClosureArgument(new Reference(AlmanacItems::ALMANAC)), new Reference(Calendar::class), $config['default_timezone']])->public();

            if (class_exists(Command::class)) {
                $services->set(ImportAlmanacCommand::class)->args([new Reference(AlmanacImport::class)])->tag('console.command');
            }
        }

        $services->set(CalendarViews::class)
            ->args([new Reference(Items::class), new Reference(DoctrineVenueRepository::class), $config['default_timezone'], $config['first_weekday'], new Reference(HostLabels::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)])
            ->public();

        if (class_exists(CalendarTemplate::class)) {
            $services->set(PrintableCalendar::class)->args([new Reference(Items::class), $config['default_timezone'], $config['first_weekday']])->public();
        }

        if (class_exists(AsLiveComponent::class)) {
            $services->set(CalendarComponent::class)
                ->args([new Reference(CalendarViews::class), new Reference('clock'), new Reference(ExpansionBudget::class)])
                ->autoconfigure();
        }

        if (class_exists(AsTwigFunction::class)) {
            // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
            $services->set(KitComponents::class)->args([$config['kit_subdirectory']]);
            $services->set(CalendarExtension::class)
                ->args([new Reference(Calendar::class), new Reference(NodeCollector::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(EventTexts::class), new Reference(Attendees::class), new Reference(PersonalCalendar::class), new Reference(KitComponents::class), new Reference(CalendarChoices::class)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
