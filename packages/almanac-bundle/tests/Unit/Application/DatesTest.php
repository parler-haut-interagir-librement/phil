<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\Almanac\ComputedDate;
use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\RuleParser;
use Phil\AlmanacBundle\Application\AlmanacFactory;
use Phil\AlmanacBundle\Application\Definitions;
use Phil\AlmanacBundle\Domain\Model\StoredDate;
use Phil\AlmanacBundle\Tests\Unit\InMemoryStoredDateRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(StoredDate::class)]
#[CoversClass(Definitions::class)]
#[CoversClass(AlmanacFactory::class)]
final class DatesTest extends TestCase
{
    public function testAStoredDateKeepsItsDefinition(): void
    {
        $oktoberfest = new Definition('oktoberfest', 'Oktoberfest', RuleParser::parse('saturday on or after 09-15'), RuleParser::parse('sunday on or after 10-03'), tags: ['fete']);
        $stored = new StoredDate($oktoberfest);

        self::assertSame('oktoberfest', $stored->key());
        self::assertSame(7, '7' === $stored->id()->toRfc4122()[14] ? 7 : 0, 'a UUID v7');
        self::assertEquals($oktoberfest, $stored->definition());

        $stored->change(new Definition('oktoberfest', 'Moon', RuleParser::parse('full moon'), days: 1, allDay: false, tags: []));
        $moon = $stored->definition();
        self::assertSame(['oktoberfest', 'Moon', 'full moon', null, 1, false, []], [$moon->key, $moon->title, (string) $moon->rule, $moon->until, $moon->days, $moon->allDay, $moon->tags]);

        $stored->change(new Definition('oktoberfest', 'Week', RuleParser::parse('monday on or before 02-22'), days: 7));
        self::assertSame([7, true, null], [$stored->definition()->days, $stored->definition()->allDay, $stored->definition()->until]);

        $this->expectException(InvalidRule::class);
        $this->expectExceptionMessageIsOrContains('The date "oktoberfest" keeps its key; "another" is another date.');
        $stored->change(new Definition('another', 'Moon', RuleParser::parse('full moon')));
    }

    /**
     * A stored date replaces the configured one of its key; the others stay, the configuration's first.
     */
    public function testTheDefinitions(): void
    {
        $stored = new InMemoryStoredDateRepository();
        $definitions = new Definitions([
            new Definition('noel', 'Noël', RuleParser::parse('12-25')),
            new Definition('thanksgiving', 'Action de grâce', RuleParser::parse('2nd monday of october')),
        ], $stored);

        self::assertSame(['noel', 'thanksgiving'], $this->keys($definitions->all()));

        $stored->save(new StoredDate(new Definition('thanksgiving', 'Thanksgiving', RuleParser::parse('4th thursday of november'))));
        $stored->save(new StoredDate(new Definition('mardi-gras', 'Mardi gras', RuleParser::parse('easter - 47 days'))));

        self::assertSame(['noel', 'thanksgiving', 'mardi-gras'], $this->keys($definitions->all()));
        self::assertSame('4th thursday of november', (string) $definitions->all()[1]->rule);
        self::assertSame(['mardi-gras', 'thanksgiving'], array_map(static fn (StoredDate $date): string => $date->key(), $definitions->stored()));
        self::assertSame([true, false], [$definitions->isConfigured('thanksgiving'), $definitions->isConfigured('mardi-gras')]);

        $broken = new StoredDate(new Definition('broken', 'Cassé', RuleParser::parse('12-25')));
        new ReflectionProperty(StoredDate::class, 'rule')->setValue($broken, 'every blue moon');
        $stored->save($broken);
        self::assertSame(['noel', 'thanksgiving', 'mardi-gras'], $this->keys($definitions->all()), 'a stored row whose rule no longer reads is left out');

        $alone = new Definitions([new Definition('noel', 'Noël', RuleParser::parse('12-25'))]);
        self::assertSame(['noel'], $this->keys($alone->all()));
        self::assertSame([], $alone->stored());
    }

    public function testTheAlmanacSeesAStoredDateAtOnce(): void
    {
        $stored = new InMemoryStoredDateRepository();
        $factory = new AlmanacFactory(new Definitions([new Definition('noel', 'Noël', RuleParser::parse('12-25'))], $stored), 'America/Toronto');
        $from = new DateTimeImmutable('2026-12-24 00:00 America/Toronto');
        $until = new DateTimeImmutable('2026-12-26 00:00 America/Toronto');

        self::assertSame(['noel'], $this->dates($factory->almanac()->between($from, $until)));

        $stored->save(new StoredDate(new Definition('reveillon', 'Réveillon', RuleParser::parse('12-24'))));
        self::assertSame(['reveillon', 'noel'], $this->dates($factory->almanac()->between($from, $until)));
        self::assertSame(['noel'], $this->dates($factory->almanac()->between(new DateTimeImmutable('2026-12-26 04:59 UTC'), new DateTimeImmutable('2026-12-26 05:00 UTC'))), 'still December 25 in Toronto');
        self::assertSame([], $this->dates($factory->almanac()->between(new DateTimeImmutable('2026-12-26 05:00 UTC'), new DateTimeImmutable('2026-12-26 06:00 UTC'))));
    }

    /**
     * @param list<Definition> $definitions
     *
     * @return list<string>
     */
    private function keys(array $definitions): array
    {
        return array_map(static fn (Definition $definition): string => $definition->key, $definitions);
    }

    /**
     * @param list<ComputedDate> $dates
     *
     * @return list<string>
     */
    private function dates(array $dates): array
    {
        return array_map(static fn (ComputedDate $date): string => $date->definition->key, $dates);
    }
}
