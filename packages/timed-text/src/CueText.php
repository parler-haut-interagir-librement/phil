<?php

declare(strict_types=1);

namespace Phil\TimedText;

use Phil\TimedText\Internal\CueToken;

/**
 * WebVTT cue text, the text of every cue of the model: reading its voices and plain text, building it from plain text
 * or SRT markup, and writing it as SRT or as HTML.
 *
 * The HTML is escaped by construction: every run of text and every attribute value goes through `htmlspecialchars()`,
 * only `b`, `i`, `u`, `ruby`, `rt` and `span` are written, and a class or a language that is not a plain token is
 * dropped. A cue text from any source can be printed as it comes out.
 */
final class CueText
{
    private const array HTML_TAGS = ['b' => 'b', 'i' => 'i', 'u' => 'u', 'ruby' => 'ruby', 'rt' => 'rt', 'c' => 'span', 'v' => 'span', 'lang' => 'span'];

    private const array SRT_TAGS = ['b' => 'b', 'i' => 'i', 'u' => 'u'];

    private function __construct()
    {
    }

    /**
     * The text without its markup, its character references decoded.
     */
    public static function plain(string $text): string
    {
        $plain = '';

        foreach (CueToken::split($text) as $token) {
            if (CueToken::TEXT === $token->kind) {
                $plain .= self::decode($token->value);
            }
        }

        return $plain;
    }

    /**
     * The voice that opens the text, `Alice` for `<v Alice>Hello`; null without one.
     */
    public static function speaker(string $text): ?string
    {
        $first = CueToken::split($text)[0] ?? null;

        return null !== $first && CueToken::OPEN === $first->kind && 'v' === $first->name && '' !== $first->annotation
            ? self::decode($first->annotation)
            : null;
    }

    /**
     * Every voice the text names, once each, in order.
     *
     * @return list<string>
     */
    public static function voices(string $text): array
    {
        $voices = [];

        foreach (CueToken::split($text) as $token) {
            if (CueToken::OPEN === $token->kind && 'v' === $token->name && '' !== $token->annotation) {
                $voices[] = self::decode($token->annotation);
            }
        }

        return array_values(array_unique($voices));
    }

    /**
     * Cue text from plain text: `&`, `<` and `>` escaped, line breaks normalised, empty lines removed, and the text
     * opened by the voice of the speaker when one is given.
     */
    public static function fromPlain(string $text, ?string $speaker = null): string
    {
        $body = self::lines(self::escape($text));
        $speaker = null === $speaker ? '' : trim((string) preg_replace('/\s+/', ' ', $speaker));

        return '' === $speaker ? $body : '<v '.self::escape($speaker).'>'.$body;
    }

    /**
     * Cue text from the text of an SRT cue: `<b>`, `<i>` and `<u>` kept, `<font>` and every other tag dropped, as are
     * the `{\an8}` overrides some editors write; the rest escaped.
     */
    public static function fromSrt(string $text): string
    {
        $cue = '';

        foreach (CueToken::split((string) preg_replace('/\{\\\\[^{}]*\}/', '', $text)) as $token) {
            $name = strtolower($token->name);
            $cue .= match ($token->kind) {
                CueToken::TEXT => self::escape($token->value),
                CueToken::OPEN => isset(self::SRT_TAGS[$name]) ? '<'.$name.'>' : '',
                CueToken::CLOSE => isset(self::SRT_TAGS[$name]) ? '</'.$name.'>' : '',
                default => self::escape($token->value),
            };
        }

        return self::lines($cue);
    }

    /**
     * The text as an SRT cue carries it: `<b>`, `<i>` and `<u>` kept, every other tag dropped, the character
     * references decoded.
     */
    public static function toSrt(string $text): string
    {
        return self::render($text, self::SRT_TAGS, static fn (string $plain): string => $plain, static fn (CueToken $token): string => '');
    }

    /**
     * The text as HTML: `b`, `i`, `u`, `ruby` and `rt` as themselves, a class span `<c.loud>` as
     * `<span class="loud">`, a voice as `<span class="voice" data-voice="Alice">`, a language span as `<span lang>`,
     * line breaks as `<br>`. The timestamps inside a cue are dropped; the tags left open are closed.
     */
    public static function toHtml(string $text): string
    {
        return self::render(
            $text,
            self::HTML_TAGS,
            static fn (string $plain): string => str_replace("\n", "<br>\n", self::html($plain)),
            static function (CueToken $token): string {
                $classes = array_values(array_filter($token->classes, static fn (string $class): bool => 1 === preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $class)));

                if ('v' === $token->name) {
                    array_unshift($classes, 'voice');
                }

                $attributes = [] === $classes ? '' : ' class="'.self::html(implode(' ', $classes)).'"';

                if ('v' === $token->name && '' !== $token->annotation) {
                    $attributes .= ' data-voice="'.self::html(self::decode($token->annotation)).'"';
                }

                if ('lang' === $token->name && 1 === preg_match('/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8}){0,8}$/D', $token->annotation)) {
                    $attributes .= ' lang="'.$token->annotation.'"';
                }

                return $attributes;
            },
        );
    }

    /**
     * `&`, `<` and `>` as WebVTT character references.
     */
    public static function escape(string $text): string
    {
        return strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }

    /**
     * @param array<string, string>      $tags       the cue tags written, and the tag each becomes
     * @param callable(string): string   $plain      writes a run of plain text
     * @param callable(CueToken): string $attributes the attributes of an opening tag
     */
    private static function render(string $text, array $tags, callable $plain, callable $attributes): string
    {
        $out = '';
        $open = [];

        foreach (CueToken::split($text) as $token) {
            if (CueToken::TEXT === $token->kind) {
                $out .= $plain(self::decode($token->value));
            } elseif (CueToken::OPEN === $token->kind && isset($tags[$token->name])) {
                $open[] = $token->name;
                $out .= '<'.$tags[$token->name].$attributes($token).'>';
            } elseif (CueToken::CLOSE === $token->kind && \in_array($token->name, $open, true)) {
                do {
                    $name = (string) array_pop($open);
                    $out .= '</'.$tags[$name].'>';
                } while ($name !== $token->name);
            }
        }

        foreach (array_reverse($open) as $name) {
            $out .= '</'.$tags[$name].'>';
        }

        return $out;
    }

    private static function decode(string $text): string
    {
        return html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    private static function html(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5 | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Line breaks as `\n`, without the empty lines that would end a cue.
     */
    private static function lines(string $text): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $text);

        if (false === $lines) {
            return '';
        }

        return implode("\n", array_filter($lines, static fn (string $line): bool => '' !== trim($line)));
    }
}
