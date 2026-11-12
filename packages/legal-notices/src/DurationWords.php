<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use Phil\LegalNotices\Exception\InvalidLegalNotice;

/**
 * Retention periods in words, in English and in French — the language of a locale, English for any other —: `P2Y6M`
 * is "2 years and 6 months", "2 ans et 6 mois". An ISO 8601 duration is read here with four digits at most per part, a
 * week mixing with the other parts; a duration of nothing is refused.
 */
final class DurationWords
{
    public const string ISO_8601 = '/^P(?!$)(?:(\d{1,4})Y)?(?:(\d{1,4})M)?(?:(\d{1,4})W)?(?:(\d{1,4})D)?(?:T(?=\d)(?:(\d{1,4})H)?(?:(\d{1,4})M)?(?:(\d{1,4})S)?)?$/D';

    private const array UNITS = [
        'en' => [['year', 'years'], ['month', 'months'], ['week', 'weeks'], ['day', 'days'], ['hour', 'hours'], ['minute', 'minutes'], ['second', 'seconds']],
        'fr' => [['an', 'ans'], ['mois', 'mois'], ['semaine', 'semaines'], ['jour', 'jours'], ['heure', 'heures'], ['minute', 'minutes'], ['seconde', 'secondes']],
    ];

    private const array WORDS = [
        'en' => ['and' => ' and ', 'indefinite' => 'kept with no set end', 'not_yet_fixed' => 'not set yet'],
        'fr' => ['and' => ' et ', 'indefinite' => 'conservées sans échéance fixée', 'not_yet_fixed' => 'pas encore fixée'],
    ];

    private function __construct()
    {
    }

    /**
     * The period of a retention in words; the event of a period until an event as the site wrote it.
     */
    public static function describe(RetentionDuration $retention, string $locale): string
    {
        $language = self::language($locale);

        return match ($retention->kind) {
            RetentionKind::Fixed => self::duration($retention->duration ?? '', $locale),
            RetentionKind::UntilEvent => $retention->note ?? '',
            RetentionKind::Indefinite, RetentionKind::NotYetFixed => self::WORDS[$language][$retention->kind->value],
        };
    }

    /**
     * An ISO 8601 duration in words.
     *
     * @throws InvalidLegalNotice
     */
    public static function duration(string $duration, string $locale): string
    {
        $language = self::language($locale);
        $words = [];

        foreach (self::parts($duration) as $index => $count) {
            [$one, $many] = self::UNITS[$language][$index];
            $words[] = $count.' '.(1 === $count ? $one : $many);
        }

        $last = array_pop($words);

        return [] === $words ? $last : implode(', ', $words).self::WORDS[$language]['and'].$last;
    }

    /**
     * The parts of an ISO 8601 duration that are not zero, by index: years 0, months 1, weeks 2, days 3, hours 4,
     * minutes 5, seconds 6.
     *
     * @return non-empty-array<int<0, 6>, positive-int>
     *
     * @throws InvalidLegalNotice
     */
    public static function parts(string $duration): array
    {
        if (1 !== preg_match(self::ISO_8601, $duration, $matches)) {
            throw InvalidLegalNotice::because('duration', \sprintf('%s is not an ISO 8601 duration such as "P90D" or "P2Y".', InvalidLegalNotice::quote($duration)));
        }

        $parts = [];

        foreach ([0, 1, 2, 3, 4, 5, 6] as $index) {
            $count = (int) ($matches[$index + 1] ?? '');

            if ($count > 0) {
                $parts[$index] = $count;
            }
        }

        return [] !== $parts ? $parts : throw InvalidLegalNotice::because('zero_duration', \sprintf('"%s" is a duration of nothing.', $duration));
    }

    /**
     * @return 'en'|'fr'
     */
    private static function language(string $locale): string
    {
        return 'fr' === Locales::language($locale) ? 'fr' : 'en';
    }
}
