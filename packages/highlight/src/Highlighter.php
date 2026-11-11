<?php

declare(strict_types=1);

namespace Phil\Highlight;

use Phil\Highlight\Exception\InvalidLanguage;
use Phil\Highlight\Exception\UnknownLanguage;
use Phil\Highlight\Language\Code;
use Phil\Highlight\Language\Definitions;
use Phil\Highlight\Language\Diff;
use Phil\Highlight\Language\Dockerfile;
use Phil\Highlight\Language\Ini;
use Phil\Highlight\Language\Markdown;
use Phil\Highlight\Language\Markup;
use Phil\Highlight\Language\Php;
use Phil\Highlight\Language\StyleSheet;
use Phil\Highlight\Language\Twig;
use Phil\Highlight\Language\Yaml;

/**
 * The languages, by name and alias, and the highlighting of a source in one of them. `default()` has every language
 * the library ships; `with()` adds one, or replaces the one of the same name. Names are matched in any case.
 *
 * `highlight()` has the shape a code renderer takes: the source and the name of its language, and the HTML, or `null`
 * when the language is unknown. Both it and `toHtml()` write the block line by line when given `LineOptions`.
 */
final readonly class Highlighter
{
    /**
     * @param array<string, Language> $languages each name and alias, in lower case, to its language
     */
    private function __construct(
        private array $languages,
    ) {
    }

    public static function of(Language ...$languages): self
    {
        $highlighter = new self([]);

        foreach ($languages as $language) {
            $highlighter = $highlighter->with($language);
        }

        return $highlighter;
    }

    /**
     * Every language the library ships.
     */
    public static function default(): self
    {
        $javascript = new Code(Definitions::javascript());
        $css = new StyleSheet();
        $html = Markup::html($javascript, $css);
        $bash = new Code(Definitions::bash());

        return self::of(
            new Php(new Code(Definitions::php()), $html),
            $javascript,
            new Code(Definitions::typescript()),
            new Code(Definitions::json()),
            $html,
            Markup::xml(),
            $css,
            new StyleSheet(scss: true),
            new Code(Definitions::sql()),
            new Yaml(),
            new Twig($html, new Code(Definitions::twigExpression())),
            new Markdown(),
            $bash,
            new Code(Definitions::python()),
            new Code(Definitions::ruby()),
            new Code(Definitions::go()),
            new Code(Definitions::rust()),
            new Code(Definitions::java()),
            new Code(Definitions::c()),
            new Code(Definitions::cpp()),
            new Code(Definitions::csharp()),
            new Ini(),
            new Dockerfile($bash),
            new Diff(),
        );
    }

    /**
     * The same highlighter with `$language` under its name and aliases, in place of any language that had them.
     *
     * @throws InvalidLanguage when a name or an alias cannot be asked for
     */
    public function with(Language $language): self
    {
        $languages = $this->languages;

        foreach ([$language->name(), ...$language->aliases()] as $name) {
            if (1 !== preg_match('/^[a-z0-9_+#.-]{1,32}$/D', $name)) {
                throw InvalidLanguage::name($name);
            }

            $languages[$name] = $language;
        }

        return new self($languages);
    }

    public function supports(string $language): bool
    {
        return isset($this->languages[strtolower($language)]);
    }

    /**
     * @throws UnknownLanguage
     */
    public function language(string $name): Language
    {
        return $this->languages[strtolower($name)] ?? throw UnknownLanguage::named($name, $this->languages());
    }

    /**
     * The canonical names, sorted.
     *
     * @return list<string>
     */
    public function languages(): array
    {
        $names = array_unique(array_map(static fn (Language $language): string => $language->name(), $this->languages));
        sort($names);

        return $names;
    }

    /**
     * @return list<Token>
     *
     * @throws UnknownLanguage
     */
    public function tokenize(string $code, string $language): array
    {
        return $this->language($language)->tokenize($code);
    }

    /**
     * The source as HTML, to go inside `<pre><code>`; line by line when `$lines` is given.
     *
     * @throws UnknownLanguage
     */
    public function toHtml(string $code, string $language, ?LineOptions $lines = null): string
    {
        $tokens = $this->tokenize($code, $language);

        return $lines instanceof LineOptions ? HtmlFormatter::formatLines($tokens, $lines) : HtmlFormatter::format($tokens);
    }

    /**
     * The source as HTML, or `null` when the language is unknown; line by line when `$lines` is given.
     */
    public function highlight(string $code, string $language, ?LineOptions $lines = null): ?string
    {
        return $this->supports($language) ? $this->toHtml($code, $language, $lines) : null;
    }
}
