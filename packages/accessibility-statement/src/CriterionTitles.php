<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

/**
 * The French titles of the success criteria of WCAG 2.2, as the W3C Authorized Translation publishes them, read from
 * `data/wcag-2.2-success-criteria.fr.json`. `data/NOTICE.md` names the source, its copyright and its licence.
 */
final readonly class CriterionTitles
{
    public const string FRENCH = __DIR__.'/../data/wcag-2.2-success-criteria.fr.json';

    /**
     * The title of each success criterion, by number such as `1.4.3`, in the order of WCAG.
     *
     * @return array<string, string>
     */
    public static function french(): array
    {
        /** @var array<string, string> $titles the file of the package, a flat map of strings */
        $titles = json_decode((string) file_get_contents(self::FRENCH), true, flags: \JSON_THROW_ON_ERROR);

        return $titles;
    }
}
