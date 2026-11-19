<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Application\View;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Phil\Almanac\Almanac;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\RuleParser;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\View\AlmanacImport;
use Phil\CalendarBundle\Application\View\Bar;
use Phil\CalendarBundle\Application\View\CalendarResource;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\CalendarViews;
use Phil\CalendarBundle\Application\View\HostLabels;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\Items;
use Phil\CalendarBundle\Application\View\ItemSource;
use Phil\CalendarBundle\Application\View\MonthWeek;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Infrastructure\Console\ImportAlmanacCommand;
use Phil\CalendarBundle\Infrastructure\Twig\Components\CalendarComponent;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryVenues;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CalendarViews::class)]
#[CoversClass(CalendarComponent::class)]
#[CoversClass(AlmanacImport::class)]
#[CoversClass(ImportAlmanacCommand::class)]
final class ViewsServiceTest extends ViewTestCase
{
    private function views(?HostLabels $labels = null, ?InMemoryVenues $venues = null): CalendarViews
    {
        $source = new readonly class([$this->at('Game', '2026-09-15 10:00', '2026-09-15 12:00', ['rpg'], ['shop:1', 'club:2']), $this->days('Fair', '2026-09-16', tags: ['larp', 'rpg'], hosts: ['club:2'], venue: 'v1')]) implements ItemSource {
            /** @param list<Item> $items */
            public function __construct(private array $items)
            {
            }

            public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
            {
                return $this->items;
            }
        };

        return new CalendarViews(new Items([$source]), $venues ?? new InMemoryVenues(), 'America/Toronto', 7, $labels);
    }

    public function testThePeriodAndTheLayouts(): void
    {
        $views = $this->views();
        $period = $views->period(CalendarView::Week, new DateTimeImmutable('2026-09-16 02:00 UTC'));

        self::assertSame(['2026-09-13', 'America/Toronto', 7], [$period->from->format('Y-m-d'), $period->from->getTimezone()->getName(), $period->firstWeekday]);
        self::assertSame('America/Toronto', $views->zone()->getName());
        self::assertCount(5, $views->month($views->period(CalendarView::Month, $this->day('2026-09-15')))->weeks);
        self::assertCount(1, $views->month($views->period(CalendarView::Month, $this->day('2026-09-15')), ['larp'], 1)->weeks[2]->bars);
        self::assertSame(['Game', 'Fair'], array_map(static fn (Item $item): string => $item->title, $views->agenda($period)->items()));
        self::assertSame(['Fair'], array_map(static fn (Item $item): string => $item->title, $views->agenda($period, ['larp'])->items()));
        self::assertCount(7, $views->time($period)->columns);
        self::assertSame([8, 18], [$views->time($period, null, null, 8, 18)->firstHour, $views->time($period, null, null, 8, 18)->lastHour]);
        self::assertCount(7, $views->time($period, ['rpg'], CalendarViews::TAGS)->columns, 'only the tag asked for');
        self::assertCount(7, $views->time($period, ['rpg', 'nope'], CalendarViews::TAGS)->columns, 'not a tag no item has');
        self::assertCount(14, $views->time($period, null, CalendarViews::TAGS)->columns, 'every tag of the items');
    }

    /**
     * Without a time zone, UTC and Monday weeks; three lanes a month, the whole day by default.
     */
    public function testTheDefaults(): void
    {
        $source = new readonly class([$this->days('A', '2026-09-15'), $this->days('B', '2026-09-15'), $this->days('C', '2026-09-15'), $this->days('D', '2026-09-15')]) implements ItemSource {
            /** @param list<Item> $items */
            public function __construct(private array $items)
            {
            }

            public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
            {
                return $this->items;
            }
        };
        $views = new CalendarViews(new Items([$source]), new InMemoryVenues());
        $month = $views->period(CalendarView::Month, new DateTimeImmutable('2026-09-15 12:00 UTC'));

        self::assertSame(['UTC', 1, '2026-08-31'], [$views->zone()->getName(), $month->firstWeekday, $month->from->format('Y-m-d')]);
        self::assertSame([1 => 1], array_map(\count(...), $views->month($month)->weeks[2]->hidden));
        self::assertSame([0, 24], [$views->time($month->in(CalendarView::Day))->firstHour, $views->time($month->in(CalendarView::Day))->lastHour]);
    }

    /**
     * The hosts the items have, labelled by the application or by themselves; every venue; the tags the items have.
     */
    public function testTheColumnsOfASharedDay(): void
    {
        $labels = new class implements HostLabels {
            public function labels(array $hosts): array
            {
                return ['shop:1' => 'La boutique', 'nobody:9' => 'Ignored'];
            }
        };
        $venues = new InMemoryVenues();
        $venues->save($first = new Venue('Salle B'));
        $venues->save($second = new Venue('Salle A'));

        $views = $this->views($labels, $venues);
        $items = $views->agenda($views->period(CalendarView::Week, $this->day('2026-09-15')))->items();

        self::assertSame([['club:2', 'club:2'], ['shop:1', 'La boutique'], ['', 'Autres']], $this->columns($views->resources(CalendarViews::HOSTS, $items, 'Autres')->columns()));
        self::assertSame([['club:2', 'club:2'], ['shop:1', 'shop:1']], $this->columns($this->views(null, $venues)->resources(CalendarViews::HOSTS, $items)->columns()));
        self::assertSame([[$second->id, 'Salle A'], [$first->id, 'Salle B']], $this->columns($views->resources(CalendarViews::VENUES, $items)->columns()));
        self::assertSame([['larp', 'larp'], ['rpg', 'rpg']], $this->columns($views->resources(CalendarViews::TAGS, $items)->columns()));
        self::assertSame([], $views->resources(CalendarViews::TAGS, [])->columns());
        self::assertSame(['hosts', 'venues', 'tags'], [CalendarViews::HOSTS, CalendarViews::VENUES, CalendarViews::TAGS]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A day is shared between hosts, venues or tags, not "rooms".');
        $views->resources('rooms', $items);
    }

    /**
     * The component moves through periods, switches views, falls back on today and on the first view offered.
     */
    public function testTheComponent(): void
    {
        $clock = new readonly class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-09-16 02:00 UTC');
            }
        };
        $component = new CalendarComponent($this->views(), $clock);

        self::assertSame(['month', '2026-09-15', '2026-09-15'], [$component->period()->view->value, $component->period()->date->format('Y-m-d'), $component->todayDate()->format('Y-m-d')], 'today, in Toronto');
        $component->next();
        self::assertSame('2026-10-15', $component->date);
        $component->previous();
        $component->previous();
        self::assertSame('2026-08-15', $component->date);
        $component->show('week');
        self::assertSame(['week', '2026-08-15'], [$component->view, $component->date]);
        $component->next();
        self::assertSame('2026-08-22', $component->date);
        $component->show('day', '2026-09-30');
        self::assertSame(['day', '2026-09-30'], [$component->view, $component->date]);
        $component->today();
        self::assertSame('', $component->date);
        $component->show('nope');
        self::assertSame('month', $component->view, 'an unknown view is the first offered');

        $component->views = ['list', 'posters'];
        $component->view = 'month';
        self::assertSame(CalendarView::List, $component->period()->view, 'a view not offered is the first offered');
        $component->views = [];
        self::assertSame(CalendarView::Month, $component->period()->view);

        $component->date = '2026-02-30';
        self::assertSame('2026-03-02', $component->period()->date->format('Y-m-d'), 'a date PHP reads, moved');
        $component->date = '2026-13-45';
        self::assertSame('2026-09-15', $component->period()->date->format('Y-m-d'), 'a date that cannot be read is today');
        $component->date = '15/09/2026';
        self::assertSame('2026-09-15', $component->period()->date->format('Y-m-d'));
        $component->date = "2026-09-01\n";
        self::assertSame('2026-09-15', $component->period()->date->format('Y-m-d'));
        $component->date = ' 2026-10-01';
        self::assertSame('2026-09-15', $component->period()->date->format('Y-m-d'), 'only a date and nothing around it');
        $component->date = '9999-11-01';
        self::assertSame('2026-09-15', $component->period()->date->format('Y-m-d'), 'a date too far from today is today');
        $component->date = '1500-03-01';
        self::assertSame('2026-09-15', $component->period()->date->format('Y-m-d'));
        $component->date = '2126-12-31';
        self::assertSame('2126-12-31', $component->period()->date->format('Y-m-d'), 'a hundred years on is still a date');
        $component->date = '1926-01-01';
        self::assertSame('1926-01-01', $component->period()->date->format('Y-m-d'));
        $component->date = '2127-01-01';
        self::assertSame('2026-09-15', $component->period()->date->format('Y-m-d'));
        self::assertSame(100, CalendarComponent::YEARS);

        $component->date = '2026-09-15';
        $component->views = ['month', 'week', 'day', 'list', 'posters'];
        $component->view = 'week';
        $component->tags = ['larp'];
        self::assertSame(['Fair'], array_map(static fn (Item $item): string => $item->title, $component->agenda()->items()));
        $component->view = 'month';
        self::assertSame(['Fair'], array_map(static fn (Bar $bar): string => $bar->item->title, $component->month()->weeks[2]->bars));
        $component->view = 'week';
        $component->shared = CalendarViews::TAGS;
        $component->firstHour = 8;
        $component->lastHour = 20;
        self::assertSame([7, 8, 20], [\count($component->time()->columns), $component->time()->firstHour, $component->time()->lastHour], 'the tag asked for, seven days');
        $component->tags = [];
        $component->shared = null;
        self::assertCount(7, $component->time()->columns);
        $component->lanes = 1;
        self::assertSame(1, max([0, ...array_map(static fn (MonthWeek $week): int => $week->lanes(), $component->month()->weeks)]));

        self::assertSame(DateTimeImmutable::class, $component->todayDate()::class);
        self::assertGreaterThan(new DateTimeImmutable('2000-01-01'), new CalendarComponent($this->views(), new NativeClock())->todayDate(), "the system's clock");
    }

    /**
     * The almanac's dates of a year, made events once, hosted by the almanac.
     */
    public function testTheAlmanacImport(): void
    {
        $events = new InMemoryEvents();
        $calendar = new Calendar($events);
        $almanac = new Almanac([
            new Definition('noel', 'Noël', RuleParser::parse('12-25'), tags: ['fete']),
            new Definition('fetes', 'Fêtes', RuleParser::parse('12-24'), days: 3),
            new Definition('moon', 'Pleine lune', RuleParser::parse('full moon'), allDay: false, tags: ['lune']),
        ], new DateTimeZone('America/Toronto'));
        $import = new AlmanacImport($almanac, $calendar, 'America/Toronto');

        self::assertSame([1, 0], $import->import(2026, ['fete', 'none']));
        $noel = $calendar->occurrences($this->day('2026-12-20'), $this->day('2026-12-31'), AlmanacImport::HOST, 'noel');
        self::assertSame(['Noël', '2026-12-25', '2026-12-25', true, ['fete']], [$noel[0]->event->title(), $noel[0]->start->format('Y-m-d'), $noel[0]->end->format('Y-m-d'), $noel[0]->event->isAllDay(), $noel[0]->event->tags()]);

        self::assertSame([14, 1], $import->import(2026), 'the fêtes and thirteen full moons made, Christmas kept');
        $fetes = $calendar->occurrences($this->day('2026-12-20'), $this->day('2026-12-31'), AlmanacImport::HOST, 'fetes');
        self::assertSame(['2026-12-24', '2026-12-26'], [$fetes[0]->start->format('Y-m-d'), $fetes[0]->end->format('Y-m-d')]);
        $moon = $calendar->occurrences($this->day('2026-12-20'), $this->day('2026-12-31'), AlmanacImport::HOST, 'moon');
        self::assertSame(['2026-12-23', '2026-12-23', true, ['lune']], [$moon[0]->start->format('Y-m-d'), $moon[0]->end->format('Y-m-d'), $moon[0]->event->isAllDay(), $moon[0]->event->tags()], 'a phase on its day');

        self::assertSame([0, 15], $import->import(2026), 'once');
        self::assertSame([1, 0], $import->import(2027, ['fete']));
        self::assertSame('almanac', AlmanacImport::HOST);
        self::assertCount(1, $calendar->occurrences($this->day('2026-12-25'), $this->day('2026-12-26'), AlmanacImport::HOST, 'noel'));
        self::assertInstanceOf(Occurrence::class, $calendar->occurrences($this->day('2027-12-25'), $this->day('2027-12-26'), AlmanacImport::HOST, 'noel')[0]);

        $east = new Calendar(new InMemoryEvents());
        new AlmanacImport(new Almanac([new Definition('canada', 'Fête du Canada', RuleParser::parse('07-01'))], new DateTimeZone('UTC')), $east, 'America/Toronto')->import(2026);
        $day = $east->occurrences($this->day('2026-06-29'), $this->day('2026-07-04'), AlmanacImport::HOST, 'canada');
        self::assertSame(['2026-07-01', '2026-07-01'], [$day[0]->start->format('Y-m-d'), $day[0]->end->format('Y-m-d')], 'a whole day keeps its date in another time zone');
    }

    public function testTheImportCommand(): void
    {
        $calendar = new Calendar(new InMemoryEvents());
        $almanac = new Almanac([new Definition('noel', 'Noël', RuleParser::parse('12-25'), tags: ['fete']), new Definition('saint', 'Saint', RuleParser::parse('11-01'))], new DateTimeZone('America/Toronto'));
        $tester = new CommandTester(new ImportAlmanacCommand(new AlmanacImport($almanac, $calendar, 'America/Toronto')));

        self::assertSame(Command::SUCCESS, $tester->execute(['year' => '2026', '--tag' => ['fete']]));
        self::assertSame("1 events made from the almanac of 2026; 0 dates already had theirs.\n", $tester->getDisplay());
        $tester->execute(['year' => '2026']);
        self::assertSame("1 events made from the almanac of 2026; 1 dates already had theirs.\n", $tester->getDisplay());

        foreach (['26', 'next', '20266', "2026\n"] as $year) {
            self::assertSame(Command::FAILURE, $tester->execute(['year' => $year]));
            self::assertSame("The year is four digits: 2027.\n", $tester->getDisplay());
        }

        $command = new ImportAlmanacCommand(new AlmanacImport($almanac, $calendar));
        self::assertSame(['phil:calendar:import-almanac', "Makes the almanac's dates of a year events of the calendar"], [$command->getName(), $command->getDescription()]);
        self::assertTrue($command->getDefinition()->getOption('tag')->isArray());
    }

    /**
     * @param list<CalendarResource> $resources
     *
     * @return list<array{string, string}>
     */
    private function columns(array $resources): array
    {
        return array_map(static fn (CalendarResource $resource): array => [$resource->key, $resource->label], $resources);
    }
}
