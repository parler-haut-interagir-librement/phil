<?php

declare(strict_types=1);

namespace Phil\Passwords\Strength;

/**
 * The sentences of the warnings and suggestions in one language, read from the library's `translations/`, for an
 * application without a translator. An application with Symfony's translator registers the same files, in the
 * `phil_passwords` domain, and translates the keys of `Warning::translationKey()` and `Suggestion::translationKey()`.
 */
final readonly class FeedbackMessages
{
    public const string DOMAIN = 'phil_passwords';

    public const string DEFAULT_LOCALE = 'en';

    /** The locales the library translates the feedback to. */
    public const array LOCALES = ['en', 'fr'];

    private const string TRANSLATIONS = __DIR__.'/../../translations/';

    /**
     * @param array<string, string> $messages
     */
    private function __construct(
        public string $locale,
        private array $messages,
    ) {
    }

    /**
     * The messages of the locale, of its language when the library has no messages for the locale itself (`fr` for
     * `fr_CA`), or else in English.
     */
    public static function forLocale(string $locale): self
    {
        $locale = str_replace('-', '_', $locale);
        $language = explode('_', $locale)[0];
        $chosen = \in_array($locale, self::LOCALES, true) ? $locale : (\in_array($language, self::LOCALES, true) ? $language : self::DEFAULT_LOCALE);

        /** @var array<string, string> $messages */
        $messages = require self::TRANSLATIONS.self::DOMAIN.'.'.$chosen.'.php';

        return new self($chosen, $messages);
    }

    public function warning(Warning $warning): string
    {
        return $this->messages[$warning->translationKey()];
    }

    public function suggestion(Suggestion $suggestion): string
    {
        return $this->messages[$suggestion->translationKey()];
    }

    /**
     * The warning, if any, then the suggestions, each a sentence.
     *
     * @return list<string>
     */
    public function describe(Feedback $feedback): array
    {
        return [
            ...($feedback->warning instanceof Warning ? [$this->warning($feedback->warning)] : []),
            ...array_map($this->suggestion(...), $feedback->suggestions),
        ];
    }
}
