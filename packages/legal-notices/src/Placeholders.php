<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Text;

/**
 * The values a body names between `${` and `}` — `${publisher_name}`, `${retention.R-001}`, `${consent.analytics}` —, filled
 * into its HTML once it is rendered. No format `phil/markup` reads gives `${…}` a meaning, where the wiki formats read
 * `[[…]]` as a link and `{{…}}` as an image or a template, so the marker reaches the HTML as written. A value is filled
 * into the text of the HTML only, as text, its line breaks kept as `<br>`: a placeholder written in a tag — an `href`,
 * a `src`, any attribute —, in a comment, in a template or in the text of an element the browser does not read as
 * HTML, such as `<script>` or `<style>`, is left as written, for `LegalNotices::check()` to report, as is a placeholder
 * without a value.
 */
final readonly class Placeholders
{
    public const string PATTERN = '/\$\{([a-z][a-z0-9_]{0,63}(?:\.[A-Za-z0-9][A-Za-z0-9._-]{0,63})?)\}/';

    /**
     * The elements whose text the HTML serializer writes as it is, without escaping it: a value filled there could close
     * the element and open another one.
     */
    private const array RAW_TEXT = ['iframe', 'noembed', 'noframes', 'noscript', 'plaintext', 'script', 'style', 'xmp'];

    /**
     * @var array<string, string> the text of each placeholder, by name, as valid UTF-8
     */
    private array $values;

    /**
     * @param array<string, string> $values the text of each placeholder, by name
     */
    public function __construct(array $values)
    {
        $kept = [];

        // An invalid byte sequence becomes U+FFFD, as the HTML parser reads it.
        foreach ($values as $name => $value) {
            $kept[$name] = htmlspecialchars_decode(htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8'), \ENT_QUOTES);
        }

        $this->values = $kept;
    }

    /**
     * The values of a version in a locale: every field of the identity, `version` and `effective_date`, the period of
     * each retention and the name of each consent category.
     *
     * @param list<RetentionDuration> $retention
     * @param list<ConsentCategory>   $consent
     */
    public static function of(LegalDocumentVersion $version, SiteIdentity $identity, array $retention, array $consent, string $locale, string $fallback): self
    {
        $values = $identity->values();
        $values['version'] = (string) $version->number;
        $values['effective_date'] = $version->effectiveAt->format('Y-m-d');

        foreach ($retention as $duration) {
            $values['retention.'.$duration->id] = DurationWords::describe($duration, $locale);
        }

        foreach ($consent as $category) {
            $values['consent.'.$category->key] = $category->label($locale, $fallback);
        }

        return new self($values);
    }

    /**
     * The names a text gives between `${` and `}`, each once, in the order they come.
     *
     * @return list<string>
     */
    public static function names(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The names a text gives that have no value.
     *
     * @return list<string>
     */
    public function unknown(string $text): array
    {
        return array_values(array_filter(self::names($text), fn (string $name): bool => !isset($this->values[$name])));
    }

    /**
     * The names of a rendered HTML that have a value but that `fill()` leaves as written, each once: written in a tag,
     * in a comment, in a template or in the text of a `<script>`, a `<style>` or another element whose text the
     * browser does not read as HTML.
     *
     * @return list<string>
     */
    public function misplaced(string $html): array
    {
        $blank = new self(array_map(static fn (): string => '', $this->values));

        return array_values(array_filter(self::names($blank->fill($html)), fn (string $name): bool => isset($this->values[$name])));
    }

    /**
     * The HTML with each placeholder of its text that has a value replaced by it, as text: escaped, its line breaks as
     * `<br>`. The HTML is read as the content of a `<body>` and written back by the HTML serializer.
     */
    public function fill(string $html): string
    {
        if ([] === array_intersect(self::names($html), array_keys($this->values))) {
            return $html;
        }

        $document = HTMLDocument::createEmpty();
        $body = $document->createElement('body');
        $body->innerHTML = $html;
        $this->fillText($document, $body);

        return $body->innerHTML;
    }

    private function fillText(HTMLDocument $document, Element $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof Text) {
                $child->replaceWith(...$this->nodes($document, $child->data));
            } elseif ($child instanceof Element && !\in_array($child->localName, self::RAW_TEXT, true)) {
                $this->fillText($document, $child);
            }
        }
    }

    /**
     * A text, its placeholders that have a value replaced by that value, as text and `<br>`.
     *
     * @return list<Element|string>
     */
    private function nodes(HTMLDocument $document, string $text): array
    {
        $nodes = [];
        $offset = 0;
        preg_match_all(self::PATTERN, $text, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        foreach ($matches as [[$marker, $at], [$name]]) {
            if (!isset($this->values[$name])) {
                continue;
            }

            $nodes[] = substr($text, $offset, $at - $offset);

            foreach (explode("\n", $this->values[$name]) as $line => $value) {
                if (0 < $line) {
                    $nodes[] = $document->createElement('br');
                }

                $nodes[] = $value;
            }

            $offset = $at + \strlen($marker);
        }

        $nodes[] = substr($text, $offset);

        return $nodes;
    }
}
