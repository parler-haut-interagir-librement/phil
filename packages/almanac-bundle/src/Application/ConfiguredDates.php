<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Application;

use Phil\Almanac\Definition;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\RuleParser;
use Phil\AlmanacBundle\Domain\Repository\StoredDateRepository;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * The dates of `phil_almanac.dates`, read into definitions: when the container is built, to stop it on a mistake that
 * names its date, and when the almanac's definitions are first asked for.
 *
 * @phpstan-type Dates array<int|string, array{title: string, rule: string, until: ?string, days: int, all_day: bool, tags: list<string>}>
 */
final class ConfiguredDates
{
    /**
     * @param Dates $dates
     *
     * @throws InvalidConfigurationException naming the date that cannot be read
     */
    public static function check(array $dates): void
    {
        self::read($dates);
    }

    /**
     * The dates `phil_almanac.eclipses` declares, `solar-eclipse` and `lunar-eclipse` at their instant; none when it is
     * not enabled. A configured date of the same key replaces one.
     *
     * @param array{enabled: bool, solar_title: string, lunar_title: string, tags: list<string>} $eclipses
     *
     * @return Dates
     */
    public static function eclipses(array $eclipses): array
    {
        if (!$eclipses['enabled']) {
            return [];
        }

        $date = static fn (string $title, string $rule): array => ['title' => $title, 'rule' => $rule, 'until' => null, 'days' => 1, 'all_day' => false, 'tags' => $eclipses['tags']];

        return [
            'solar-eclipse' => $date($eclipses['solar_title'], 'solar eclipse'),
            'lunar-eclipse' => $date($eclipses['lunar_title'], 'lunar eclipse'),
        ];
    }

    /**
     * @param Dates $dates
     */
    public static function definitions(array $dates, ?StoredDateRepository $stored = null): Definitions
    {
        return new Definitions(self::read($dates), $stored);
    }

    /**
     * @param Dates $dates
     *
     * @return list<Definition>
     */
    private static function read(array $dates): array
    {
        $definitions = [];

        foreach ($dates as $key => $date) {
            try {
                $definitions[] = new Definition((string) $key, $date['title'], RuleParser::parse($date['rule']), null === $date['until'] ? null : RuleParser::parse($date['until']), $date['days'], $date['all_day'], $date['tags']);
            } catch (InvalidRule $invalidRule) {
                throw new InvalidConfigurationException(\sprintf('phil_almanac.dates.%s: %s', $key, $invalidRule->getMessage()), $invalidRule->getCode(), previous: $invalidRule);
            }
        }

        return $definitions;
    }
}
