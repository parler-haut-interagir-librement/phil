<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

use Phil\AccessibilityCheck\Colour\Colour;
use Phil\AccessibilityCheck\Colour\ColourParser;
use Phil\AccessibilityCheck\Exception\InvalidPolicy;
use Phil\AccessibilityCheck\Rule\GenericLinkText;

/**
 * What the check expects of a content, and how much of it it reads: the limits that refuse a content before it costs
 * too much, the first-level headings a content may hold, the contrast level and the colours a text is shown in when
 * the content does not say, the colours behind the tokens of the look, the language of a content that does not say
 * its own, the generic link texts of each language, the rules turned off or given another severity, and the locale of
 * the messages.
 *
 * The defaults suit an article shown in a page: one `h1` at most, contrast AA, black text on white, the generic link
 * texts of English, French, Spanish, German, Italian and Portuguese, and messages in English.
 */
final readonly class Policy
{
    public Colour $foreground;

    public Colour $background;

    /**
     * The colours of the tokens of the look, which UJO writes in `data-color`, by token.
     *
     * @var array<string, Colour>
     */
    public array $colourTokens;

    /**
     * The texts that do not say where a link leads, by primary language, normalized by `GenericLinkText::normalize()`.
     *
     * @var array<string, list<string>>
     */
    public array $genericLinkTexts;

    /**
     * The severities given to rules instead of their own, by identifier.
     *
     * @var array<string, Severity>
     */
    public array $severities;

    /**
     * @param int          $maxBytes      the largest HTML checked, in bytes
     * @param int          $maxDepth      the deepest nesting of elements, or of blocks and their data
     * @param int          $maxElements   the most elements of an HTML content, or blocks of a UJO document
     * @param string       $foreground    the colour of a text whose content gives none, an opaque CSS colour
     * @param string       $background    the colour behind a text whose content gives none, an opaque CSS colour
     * @param ?string      $language      the language of a content that does not say its own, a BCP 47 tag
     * @param string       $locale        the locale of the messages of the report
     * @param list<string> $disabledRules the identifiers of the rules turned off
     */
    public function __construct(
        public int $maxBytes = 5_242_880,
        public int $maxDepth = 256,
        public int $maxElements = 100_000,
        public HeadingPolicy $headings = HeadingPolicy::Single,
        public ContrastLevel $contrast = ContrastLevel::AA,
        string $foreground = '#000000',
        string $background = '#ffffff',
        public ?string $language = null,
        public string $locale = 'en',
        public array $disabledRules = [],
    ) {
        $this->atLeastOne('maximum size', $maxBytes);
        $this->atLeastOne('maximum depth', $maxDepth);
        $this->atLeastOne('maximum number of elements', $maxElements);

        $this->foreground = $this->opaque('foreground', $foreground);
        $this->background = $this->opaque('background', $background);
        $this->colourTokens = [];
        $this->genericLinkTexts = GenericLinkText::DEFAULTS;
        $this->severities = [];
    }

    /**
     * The same policy with these tokens of the look and their colours, added to or replacing those it has.
     *
     * @param array<string, string> $tokens CSS colours by token, such as `['accent' => '#b00020']`
     */
    public function withColourTokens(array $tokens): self
    {
        $colours = [];

        foreach ($tokens as $token => $value) {
            $colours[$token] = ColourParser::parse($value) ?? throw InvalidPolicy::colour('colour of the token "'.$token.'"', $value);
        }

        return clone ($this, ['colourTokens' => $colours + $this->colourTokens]);
    }

    /**
     * The same policy with these generic link texts for a language, replacing those it had; none turns the language
     * off.
     */
    public function withGenericLinkTexts(string $language, string ...$texts): self
    {
        $normalized = array_values(array_unique(array_map(GenericLinkText::normalize(...), $texts)));

        return clone ($this, ['genericLinkTexts' => [strtolower($language) => $normalized] + $this->genericLinkTexts]);
    }

    /**
     * The same policy without these rules.
     */
    public function withoutRules(string ...$rules): self
    {
        return clone ($this, ['disabledRules' => array_values(array_unique([...$this->disabledRules, ...$rules]))]);
    }

    /**
     * The same policy where a rule's findings take this severity instead of their own.
     */
    public function withSeverity(string $rule, Severity $severity): self
    {
        return clone ($this, ['severities' => [$rule => $severity] + $this->severities]);
    }

    public function isEnabled(string $rule): bool
    {
        return !\in_array($rule, $this->disabledRules, true);
    }

    private function atLeastOne(string $name, int $value): void
    {
        if ($value < 1) {
            throw InvalidPolicy::limit($name, $value);
        }
    }

    private function opaque(string $name, string $value): Colour
    {
        $colour = ColourParser::parse($value);

        if (!$colour instanceof Colour || !$colour->isOpaque()) {
            throw InvalidPolicy::colour($name, $value);
        }

        return $colour;
    }
}
