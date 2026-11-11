<?php

declare(strict_types=1);

namespace Phil\Ujo\Html;

use Generator;
use Phil\Ujo\Exception\InvalidConfiguration;

/**
 * Keeps of an HTML fragment the elements a profile allows, written back in one canonical form, and nothing else.
 *
 * The fragment is read token by token: text, start tags with their attributes, end tags and comments. Text is decoded
 * and escaped again. An element no tool of the profile writes is unwrapped — its text is kept —, except `script`,
 * `style` and the other elements whose content is not text, dropped with it unless the profile reads them, as `rich`
 * reads `math`. A tool kept only within another is unwrapped elsewhere: a ruby's reading outside a ruby. Attributes a tool does not name are
 * dropped, and those it names are cleaned. Elements are closed in order: a stray end tag is ignored, and what is still
 * open at the end is closed. A link is never nested in a link.
 */
final readonly class InlineSanitizer
{
    /** The elements whose content is dropped with them. */
    public const array DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'textarea', 'select', 'svg', 'math', 'title', 'head', 'frameset', 'frame', 'applet', 'audio', 'video', 'canvas', 'form', 'annotation-xml'];

    private const array VOID = ['br', 'hr', 'img', 'wbr'];

    private const string TOKEN = '~<!--.*?(?:-->|$)|<(/?)([A-Za-z][A-Za-z0-9-]*)((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>~s';

    private const string ATTRIBUTE = '~([^\s"\'>/=]+)(?:\s*=\s*("[^"]*"|\'[^\']*\'|[^\s"\'=<>`]+))?~';

    /** @var array<string, InlineProfile> */
    private array $profiles;

    /**
     * @param array<string, InlineProfile> $profiles profiles added to, or replacing, `rich`, `plain`, `mathml` and `raw`
     */
    public function __construct(array $profiles = [])
    {
        $this->profiles = [...['rich' => InlineProfile::rich(), 'plain' => InlineProfile::plain(), 'mathml' => InlineProfile::mathMl(), 'raw' => InlineProfile::raw()], ...$profiles];
    }

    public function withProfile(string $name, InlineProfile $profile): self
    {
        return new self([...$this->profiles, $name => $profile]);
    }

    public function profile(string $name): InlineProfile
    {
        return $this->profiles[$name] ?? throw InvalidConfiguration::unknownProfile($name);
    }

    /**
     * The fragment with only what the profile allows, in canonical form.
     */
    public function sanitize(string $html, string $profile = 'rich'): string
    {
        $tools = $this->profile($profile);
        $clean = '';
        /** @var array<int, array{string, string}> $open the tag each open element was read as, and written as ('' when unwrapped), up to `$depth` */
        $open = [];
        $depth = 0;
        /** @var array<string, int> $opened how many elements of each tag are open, so a stray end tag costs nothing */
        $opened = [];
        /** @var array<string, int> $inside how many elements written as each tag are open */
        $inside = [];

        foreach ($this->tokens($html, $tools) as [$kind, $name, $attributes]) {
            if ('text' === $kind) {
                $clean .= self::escape($name);

                continue;
            }

            if ('end' === $kind) {
                if (isset($opened[$name]) && 0 < $opened[$name]) {
                    do {
                        [$read, $written] = $open[--$depth];
                        --$opened[$read];
                        --$inside[$written];
                        $clean .= $this->end($written);
                    } while ($read !== $name);
                }

                continue;
            }

            $match = $tools->match($name, $attributes, $inside);
            $tag = '';

            if (null !== $match && ('a' !== $match[0]->tag || 0 === ($inside['a'] ?? 0))) {
                [$tool, $kept] = $match;
                $tag = $tool->tag;
                $clean .= '<'.$tag.$this->attributes($kept).'>';
            }

            if (!\in_array($name, self::VOID, true)) {
                $open[$depth++] = [$name, $tag];
                $opened[$name] = ($opened[$name] ?? 0) + 1;
                $inside[$tag] = ($inside[$tag] ?? 0) + 1;
            }
        }

        while ($depth > 0) {
            $clean .= $this->end($open[--$depth][1]);
        }

        return $clean;
    }

    /**
     * The text of the fragment, decoded, without its elements; a line break becomes a new line.
     */
    public function toText(string $html): string
    {
        $text = '';

        foreach ($this->tokens($html, $this->profiles['rich']) as [$kind, $name]) {
            $text .= match (true) {
                'text' === $kind => $name,
                'start' === $kind && 'br' === $name => "\n",
                default => '',
            };
        }

        return $text;
    }

    /**
     * Escapes a text for HTML, as content or as the value of an attribute.
     */
    public static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * The tokens of a fragment: `['text', decoded text, []]`, `['start', tag, attributes]` and `['end', tag, []]`, tag
     * names in lower case; comments and the content of the elements dropped — those of `DROPPED` the profile does not
     * read — are left out.
     *
     * @return Generator<int, array{string, string, array<string, string>}>
     */
    private function tokens(string $html, InlineProfile $profile): Generator
    {
        preg_match_all(self::TOKEN, $html, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);
        $at = 0;
        $dropping = null;

        foreach ($matches as $match) {
            $text = substr($html, $at, $match[0][1] - $at);
            $at = $match[0][1] + \strlen($match[0][0]);
            $name = strtolower($match[2][0] ?? '');
            $end = '/' === ($match[1][0] ?? '');

            if (null !== $dropping) {
                $dropping = $end && $name === $dropping ? null : $dropping;

                continue;
            }

            if ('' !== $text) {
                yield ['text', $this->decode($text), []];
            }

            if ('' === $name) {
                continue;
            }

            if (\in_array($name, self::DROPPED, true) && !$profile->reads($name)) {
                $dropping = $end ? null : $name;
            } else {
                yield [$end ? 'end' : 'start', $name, $end ? [] : $this->parse($match[3][0] ?? '')];
            }
        }

        $rest = substr($html, $at);

        if (null === $dropping && '' !== $rest) {
            yield ['text', $this->decode($rest), []];
        }
    }

    /**
     * @return array<string, string>
     */
    private function parse(string $attributes): array
    {
        preg_match_all(self::ATTRIBUTE, $attributes, $matches, \PREG_SET_ORDER | \PREG_UNMATCHED_AS_NULL);
        $parsed = [];

        foreach ($matches as $match) {
            $value = $match[2] ?? '';
            $parsed[strtolower($match[1])] ??= $this->decode(\in_array($value[0] ?? '', ['"', "'"], true) ? substr($value, 1, -1) : $value);
        }

        return $parsed;
    }

    /**
     * The end tag of an element written as `$tag`; nothing for an element unwrapped.
     */
    private function end(string $tag): string
    {
        return '' === $tag ? '' : '</'.$tag.'>';
    }

    private function decode(string $text): string
    {
        return html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<string, string> $attributes
     */
    private function attributes(array $attributes): string
    {
        $written = '';

        foreach ($attributes as $name => $value) {
            $written .= ' '.$name.'="'.self::escape($value).'"';
        }

        return $written;
    }
}
