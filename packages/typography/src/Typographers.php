<?php

declare(strict_types=1);

namespace Phil\Typography;

use Closure;
use Dom\HTMLDocument;
use Phil\Typography\Exception\InvalidLocale;
use Phil\Typography\Exception\TypographyException;
use Phil\Typography\Exception\UnknownFixer;
use Phil\Typography\Exception\UnsupportedLanguage;
use Phil\Typography\Fixer\Fixer;
use Phil\Typography\Html\Blocks;
use Phil\Typography\Html\Serializer;
use Phil\Typography\Hyphenation\Dictionary;
use Phil\Typography\Text\Run;

/**
 * One typographer per locale, built on first use and kept: the preset of the locale, or the fixers configured for it
 * by name. The fixers of a locale are found by its candidates, so those configured for `fr` serve `fr_BE` too. The
 * typographers of 64 locales are kept at most: past them, they are built again, so that locales taken from requests
 * cannot grow the memory of a long-running process without end.
 */
final class Typographers
{
    /** The locales whose typographers are kept at most. */
    private const int KEPT = 64;

    /**
     * @var array<string, Typographer>
     */
    private array $typographers = [];

    private readonly Locale $default;

    private readonly Dictionary $dictionary;

    /**
     * @param array<string, list<string>> $fixers      fixer names by locale, in place of its preset
     * @param list<string>                $protected   the names of the elements whose text is never changed
     * @param bool                        $hyphenation whether presets hyphenate the languages the library has patterns for
     *
     * @throws InvalidLocale
     */
    public function __construct(
        string $default = 'en',
        private readonly array $fixers = [],
        private readonly array $protected = Typographer::PROTECTED_TAGS,
        private readonly bool $hyphenation = true,
        ?Dictionary $dictionary = null,
    ) {
        $this->default = Locale::parse($default);
        $this->dictionary = $dictionary ?? new Dictionary();
    }

    /**
     * The typographer of the locale, or of the default locale when null.
     *
     * @throws InvalidLocale
     * @throws UnknownFixer
     * @throws UnsupportedLanguage
     */
    public function for(Locale|string|null $locale = null): Typographer
    {
        $locale = match (true) {
            null === $locale => $this->default,
            \is_string($locale) => Locale::parse($locale),
            default => $locale,
        };

        $key = $locale->__toString();

        if (isset($this->typographers[$key])) {
            return $this->typographers[$key];
        }

        if (\count($this->typographers) >= self::KEPT) {
            $this->typographers = [];
        }

        return $this->typographers[$key] = new Typographer(
            array_map(fn (string $name): Fixer => Preset::fixer($name, $locale, $this->dictionary), $this->names($locale)),
            $this->protected,
        );
    }

    /**
     * A fragment of HTML fixed by the typographer of a locale — the default one when null —, each passage whose `lang`
     * names another language fixed by the typographer of that language: its quotes, its spaces, its hyphenation. A
     * passage in a language no typographer serves is fixed as the text around it.
     *
     * @throws InvalidLocale
     * @throws UnknownFixer
     * @throws UnsupportedLanguage
     */
    public function fixHtml(string $html, Locale|string|null $locale = null): string
    {
        $typographer = $this->for($locale);
        $document = HTMLDocument::createEmpty();
        $body = $document->createElement('body');
        $body->innerHTML = $html;
        Blocks::fix($body, $this->protected, $typographer->fixRun(...), $this->passage(...), str_replace('_', '-', ($locale instanceof Locale ? $locale : Locale::parse($locale ?? $this->default->__toString()))->__toString()));

        return Serializer::inner($body);
    }

    /**
     * The fixer of a passage's language; `null` when no typographer serves it.
     *
     * @return ?Closure(Run): Run
     */
    private function passage(string $language): ?Closure
    {
        try {
            return $this->for($language)->fixRun(...);
        } catch (TypographyException) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function names(Locale $locale): array
    {
        foreach ($locale->candidates() as $candidate) {
            if (isset($this->fixers[$candidate])) {
                return $this->fixers[$candidate];
            }
        }

        return Preset::names($locale, $this->hyphenation, $this->dictionary);
    }
}
