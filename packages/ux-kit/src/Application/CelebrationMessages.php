<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

/**
 * Words a celebration says in turn to screen readers, so that a reader does not hear the same each time: a few short
 * phrases of praise in English and in French, by the language of a locale, English for any other.
 */
final readonly class CelebrationMessages
{
    /** The language whose words stand in for a language without its own. */
    public const string FALLBACK = 'en';

    /** The words of each language, in the order they are given. */
    public const array MESSAGES = [
        'en' => ['Well done!', 'Bravo!', 'Nicely done.', 'Great job!', 'You did it!', 'Brilliant!', 'Hooray!', 'Way to go!'],
        'fr' => ['Bien joué!', 'Bravo!', 'Joli travail.', 'Excellent!', 'Vous avez réussi!', 'Brillant!', 'Hourra!', 'Chapeau!'],
    ];

    /**
     * The words of a locale, by its language: `fr`, `fr_CA` and `fr-CA` give the French; any other language, and none,
     * the English.
     *
     * @return list<string>
     */
    public function of(?string $locale): array
    {
        $locale = (string) $locale;
        $language = strtolower(substr($locale, 0, strcspn($locale, '_-')));

        return self::MESSAGES[$language] ?? self::MESSAGES[self::FALLBACK];
    }
}
