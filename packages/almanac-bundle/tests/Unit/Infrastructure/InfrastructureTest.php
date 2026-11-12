<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Unit\Infrastructure;

use DateTime;
use DateTimeImmutable;
use Phil\Almanac\ComputedDate;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\RuleParser;
use Phil\AlmanacBundle\Application\AlmanacFactory;
use Phil\AlmanacBundle\Application\Definitions;
use Phil\AlmanacBundle\Infrastructure\Form\TagsTransformer;
use Phil\AlmanacBundle\Infrastructure\Twig\AlmanacExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TagsTransformer::class)]
#[CoversClass(AlmanacExtension::class)]
final class InfrastructureTest extends TestCase
{
    public function testTags(): void
    {
        $transformer = new TagsTransformer();

        self::assertSame('fete, lune', $transformer->transform(['fete', 'lune']));
        self::assertSame('', $transformer->transform(null));
        self::assertSame(['fete', 'lune'], $transformer->reverseTransform(' fete ,, lune , fete '));
        self::assertSame([], $transformer->reverseTransform(null));
        self::assertSame([], $transformer->reverseTransform(' , '));
    }

    public function testTheDatesInTwig(): void
    {
        $extension = new AlmanacExtension(new AlmanacFactory(new Definitions([
            new Definition('noel', 'Noël', RuleParser::parse('12-25'), tags: ['fete']),
            new Definition('moon', 'Pleine lune', RuleParser::parse('full moon'), allDay: false),
        ]), 'America/Toronto'));
        self::assertSame(['moon 2026-12-23', 'noel 2026-12-25'], $this->keys($extension->dates('2026-12-20', '2026-12-31')));
        self::assertSame(['noel 2026-12-25'], $this->keys($extension->dates(new DateTimeImmutable('2026-12-20'), new DateTimeImmutable('2026-12-31'), ['fete'])));
        self::assertSame([], $this->keys($extension->dates('2026-12-27', new DateTimeImmutable('2026-12-31'))));
        self::assertSame([], $this->keys($extension->dates('2026-12-26', '2026-12-27')), "text is read in the almanac's time zone: Christmas is over in Toronto");
        self::assertSame(['noel 2026-12-25'], $this->keys($extension->dates(new DateTime('2026-12-24 12:00 UTC'), new DateTime('2026-12-26 12:00 UTC'))), 'a mutable date is an instant too');
    }

    /**
     * @param list<ComputedDate> $dates
     *
     * @return list<string>
     */
    private function keys(array $dates): array
    {
        return array_map(static fn (ComputedDate $date): string => $date->definition->key.' '.$date->span->start->format('Y-m-d'), $dates);
    }
}
