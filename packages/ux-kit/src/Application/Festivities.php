<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Phil\Almanac\Almanac;
use Phil\Almanac\ComputedDate;
use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\RuleParser;

/**
 * The holidays of the seasonal decor, each a rule of phil/almanac, and the one a day falls in: a sober holiday first,
 * then the first of the list, in the order of the configuration. The dates are computed when asked, never stored.
 */
final readonly class Festivities
{
    /** The decors the `seasonal_decor` controller draws, and the poppy, which it never animates. */
    public const array DECORS = ['snow', 'hearts', 'clovers', 'eggs', 'bats', 'leaves', 'petals', 'lanterns', 'maple-leaves', 'fleurs-de-lis', 'poppy'];

    /** The decors that are sober by default: fixed, never animated, for a day of remembrance. */
    public const array SOBER = ['poppy'];

    /**
     * The holidays of a site that names none: a rule of phil/almanac for the first day, the days it lasts, and the
     * decor drawn.
     */
    public const array DEFAULTS = [
        'winter-holidays' => ['title' => 'Winter holidays', 'rule' => '12-20', 'days' => 14, 'decor' => 'snow'],
        'lunar-new-year' => ['title' => 'Lunar New Year', 'rule' => 'chinese new year', 'days' => 3, 'decor' => 'lanterns'],
        'valentines-day' => ['title' => 'Valentine’s Day', 'rule' => '02-14', 'days' => 1, 'decor' => 'hearts'],
        'st-patricks-day' => ['title' => 'St. Patrick’s Day', 'rule' => '03-17', 'days' => 1, 'decor' => 'clovers'],
        'spring' => ['title' => 'Spring', 'rule' => 'march equinox', 'days' => 7, 'decor' => 'petals'],
        'easter' => ['title' => 'Easter', 'rule' => 'easter - 2 days', 'days' => 4, 'decor' => 'eggs'],
        'autumn' => ['title' => 'Autumn', 'rule' => 'september equinox', 'days' => 7, 'decor' => 'leaves'],
        'halloween' => ['title' => 'Halloween', 'rule' => '10-31 - 6 days', 'days' => 7, 'decor' => 'bats'],
        'remembrance-day' => ['title' => 'Remembrance Day', 'rule' => '11-11', 'days' => 1, 'decor' => 'poppy'],
    ];

    private Almanac $almanac;

    /** @var array<string, array{string, bool}> the decor and the sobriety of each holiday, in the order given */
    private array $decors;

    /**
     * @param array<string, array{title: string, rule: string, until?: ?string, days?: int, decor: string, sober?: ?bool}> $holidays by key
     *
     * @throws InvalidArgumentException for a decor that is not one of `DECORS`, or a rule phil/almanac cannot read
     */
    public function __construct(
        array $holidays,
        private DateTimeZone $zone,
    ) {
        $definitions = [];
        $decors = [];

        foreach ($holidays as $key => $holiday) {
            if (!\in_array($holiday['decor'], self::DECORS, true)) {
                throw new InvalidArgumentException(\sprintf('The decor "%s" of the holiday "%s" is none of "%s".', $holiday['decor'], $key, implode('", "', self::DECORS)));
            }

            $until = $holiday['until'] ?? null;

            try {
                $definitions[] = new Definition($key, $holiday['title'], RuleParser::parse($holiday['rule']), null === $until ? null : RuleParser::parse($until), $holiday['days'] ?? 1);
            } catch (InvalidRule $invalid) {
                throw new InvalidArgumentException(\sprintf('The holiday "%s": %s', $key, $invalid->getMessage()), 0, $invalid);
            }

            $decors[$key] = [$holiday['decor'], $holiday['sober'] ?? \in_array($holiday['decor'], self::SOBER, true)];
        }

        $this->almanac = new Almanac($definitions, $zone);
        $this->decors = $decors;
    }

    /**
     * The holiday a moment falls in, now without one: a sober one before any other, then the first in the order of
     * the configuration; null on a day of none.
     */
    public function at(?DateTimeInterface $moment = null): ?Festivity
    {
        $from = $moment instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($moment) : new DateTimeImmutable('now', $this->zone);
        $found = [];

        foreach ($this->almanac->between($from, $from->modify('+1 second')) as $date) {
            $found[$date->definition->key] = $date;
        }

        $chosen = null;

        foreach ($this->decors as $key => [, $sober]) {
            if (isset($found[$key]) && (null === $chosen || ($sober && !$this->decors[$chosen->definition->key][1]))) {
                $chosen = $found[$key];
            }
        }

        return null === $chosen ? null : $this->festivity($chosen);
    }

    private function festivity(ComputedDate $date): Festivity
    {
        [$decor, $sober] = $this->decors[$date->definition->key];

        return new Festivity($date->definition->key, $date->definition->title, $decor, $sober, $date->span->start);
    }
}
