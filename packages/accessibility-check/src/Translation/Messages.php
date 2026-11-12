<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Translation;

use Phil\AccessibilityCheck\Message;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The messages of the rules for `symfony/translation`, in the domain `phil_accessibility_check`: English, French,
 * Spanish, German, Italian and Portuguese, English the fallback. `translator()` gives a translator that holds them; an
 * application adds its own messages to it, or gives its own translator with `resources()` loaded.
 *
 * A locale is read as a BCP 47 tag, `_` or `-`, in any case: `fr-ca` is read as `fr_CA`, which takes the messages of
 * `fr_CA`, then of `fr`, then the English ones. A key no locale knows is written as it is.
 */
final class Messages
{
    public const string DOMAIN = 'phil_accessibility_check';

    /**
     * The messages of each language, by locale.
     *
     * @return array<string, array<string, string>>
     */
    public static function resources(): array
    {
        return [
            English::LOCALE => English::MESSAGES,
            French::LOCALE => French::MESSAGES,
            Spanish::LOCALE => Spanish::MESSAGES,
            German::LOCALE => German::MESSAGES,
            Italian::LOCALE => Italian::MESSAGES,
            Portuguese::LOCALE => Portuguese::MESSAGES,
        ];
    }

    /**
     * A translator with the messages of the six languages, English the fallback.
     */
    public static function translator(): Translator
    {
        $translator = new Translator(English::LOCALE);
        $translator->addLoader('array', new ArrayLoader());
        $translator->setFallbackLocales([English::LOCALE]);

        foreach (self::resources() as $locale => $messages) {
            $translator->addResource('array', $messages, $locale, self::DOMAIN);
        }

        return $translator;
    }

    /**
     * The message in the locale, its `{name}` placeholders replaced by their values.
     */
    public static function translate(TranslatorInterface $translator, Message $message, string $locale): string
    {
        $parameters = [];

        foreach ($message->parameters as $name => $value) {
            $parameters['{'.$name.'}'] = $value;
        }

        return $translator->trans($message->key, $parameters, self::DOMAIN, self::locale($locale));
    }

    /**
     * A language tag as the translator reads it: `_` between its parts, the language in lowercase, a region of two
     * letters in uppercase, a script in title case; English for what is not a language tag, such as the locale of a
     * report read back from a file.
     */
    public static function locale(string $tag): string
    {
        if (1 !== preg_match('/^[a-z]{2,8}(?:[-_][a-z0-9]{1,8})*$/iD', $tag)) {
            return English::LOCALE;
        }

        $parts = explode('_', str_replace('-', '_', $tag));
        $locale = [strtolower(array_shift($parts))];

        foreach ($parts as $part) {
            $locale[] = match (\strlen($part)) {
                2 => strtoupper($part),
                4 => ucfirst(strtolower($part)),
                default => strtolower($part),
            };
        }

        return implode('_', $locale);
    }
}
