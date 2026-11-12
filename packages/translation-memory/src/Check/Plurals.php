<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

use Override;
use Phil\TranslationMemory\Check\Contract\Check;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Text;

/**
 * The plural forms of ICU messages: when a source holds `{count, plural, …}`, its target holds a plural argument of
 * the same name with the categories its language needs for whole numbers — `one` and `other` in French, `one`,
 * `few`, `many` and `other` in Polish —, and braces that balance.
 *
 * The categories are those of the CLDR cardinal rules for whole numbers, by primary language; a language the table
 * does not hold needs `other` only. Explicit cases such as `=0` are welcome and never required.
 */
final readonly class Plurals implements Check
{
    public const string NAME = 'plurals';

    /** The categories of whole numbers, from the CLDR cardinal plural rules, by primary language. */
    private const array CATEGORIES = [
        'one other' => ['af', 'bg', 'ca', 'da', 'de', 'el', 'en', 'es', 'et', 'eu', 'fi', 'fr', 'gl', 'hu', 'it', 'nb', 'nl', 'nn', 'no', 'pt', 'sv', 'tr'],
        'one few other' => ['bs', 'cs', 'hr', 'lt', 'sk', 'sr'],
        'one few many other' => ['be', 'pl', 'ru', 'uk'],
        'one two other' => ['he'],
        'one two few many other' => ['ga'],
        'zero one two few many other' => ['ar', 'cy'],
    ];

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(Checked $checked): array
    {
        [$plurals] = IcuPlurals::read(Text::plain($checked->source));

        if ([] === $plurals) {
            return [];
        }

        [$found, $balanced] = IcuPlurals::read(Text::plain($checked->target));

        if (!$balanced) {
            return [new CheckIssue(self::NAME, 'The braces of the target do not balance.')];
        }

        $byName = [];

        foreach ($found as [$name, $selectors]) {
            $byName[$name] = [...$byName[$name] ?? [], ...$selectors];
        }

        $needed = self::categories($checked->targetLanguage);
        $issues = [];

        foreach (array_unique(array_column($plurals, 0)) as $name) {
            if (!isset($byName[$name])) {
                $issues[] = new CheckIssue(self::NAME, \sprintf('The target has no plural for “%s”.', $name));

                continue;
            }

            $missing = array_diff($needed, $byName[$name]);

            if ([] !== $missing) {
                $issues[] = new CheckIssue(self::NAME, \sprintf('The plural of “%s” lacks the forms %s.', $name, implode(', ', $missing)));
            }
        }

        return $issues;
    }

    /**
     * The categories a language needs for whole numbers.
     *
     * @return non-empty-list<string>
     */
    public static function categories(LanguageTag $language): array
    {
        $primary = explode('-', $language->value)[0];

        foreach (self::CATEGORIES as $categories => $languages) {
            if (\in_array($primary, $languages, true)) {
                return explode(' ', $categories);
            }
        }

        return ['other'];
    }
}
