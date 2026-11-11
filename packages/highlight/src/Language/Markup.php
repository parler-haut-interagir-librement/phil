<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\TokenType;

/**
 * HTML and XML: tags, attributes and their values, comments, character references, CDATA sections, doctypes and
 * processing instructions. In HTML, the content of `script` and `style` is read by the languages given for them.
 */
final readonly class Markup implements Language
{
    private const string NAME = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789:_.-';

    private const string REFERENCE = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#';

    /**
     * @param list<string>            $aliases
     * @param array<string, Language> $embedded each element, in lower case, to the language of its content
     */
    public function __construct(
        private string $name,
        private array $aliases = [],
        private array $embedded = [],
    ) {
    }

    public static function html(Language $script, Language $style): self
    {
        return new self('html', ['htm', 'xhtml', 'vue', 'svelte'], ['script' => $script, 'style' => $style]);
    }

    public static function xml(): self
    {
        return new self('xml', ['svg', 'xsl', 'xslt', 'xsd', 'rss', 'atom', 'plist', 'wsdl', 'xaml', 'mathml']);
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function aliases(): array
    {
        return $this->aliases;
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();

        while (!$cursor->done()) {
            match (true) {
                $cursor->at('&') => $this->reference($cursor, $output),
                $cursor->at('<!--') => $output->add($cursor->through('-->'), TokenType::Comment),
                $cursor->at('<![CDATA[') => $this->cdata($cursor, $output),
                $cursor->at('<!'), $cursor->at('<?') => $output->add($cursor->through('>'), TokenType::Meta),
                $cursor->at('</'), $cursor->at('<') && $cursor->in(Cursor::LETTERS, 1) => $this->tag($cursor, $output),
                default => $output->add($cursor->leadUntil('<&')),
            };
        }

        return $output->tokens();
    }

    /**
     * A character reference, `&amp;` or `&#38;`; a lone `&` is text.
     */
    private function reference(Cursor $cursor, Output $output): void
    {
        $length = strspn($cursor->text, self::REFERENCE, $cursor->position + 1);
        $output->add(...$length > 0 && ';' === $cursor->char($length + 1) ? [$cursor->take($length + 2), TokenType::Entity] : [$cursor->take(1), null]);
    }

    private function cdata(Cursor $cursor, Output $output): void
    {
        $output->add($cursor->take(9), TokenType::Meta);
        $content = $cursor->through(']]>');
        $closed = str_ends_with($content, ']]>');
        $output->add($closed ? substr($content, 0, -3) : $content);
        $output->add($closed ? ']]>' : '', TokenType::Meta);
    }

    /**
     * A start or end tag with its attributes; after the start tag of an element that has a language, its content up
     * to its end tag, read by that language.
     */
    private function tag(Cursor $cursor, Output $output): void
    {
        $closing = $cursor->at('</');
        $output->add($cursor->take($closing ? 2 : 1), TokenType::Punctuation);
        $name = $cursor->span(self::NAME);
        $output->add($name, TokenType::Tag);

        while (!$cursor->done() && !$cursor->at('>') && !$cursor->at('/>')) {
            $output->add(...match (true) {
                $cursor->in(Cursor::SPACE) => [$cursor->span(Cursor::SPACE), null],
                $cursor->in('"\'') => [$cursor->take(1).$cursor->through($cursor->previous()), TokenType::String],
                $cursor->at('=') => [$cursor->take(1), TokenType::Punctuation],
                '=' === $cursor->previous() => [$cursor->until(Cursor::SPACE, '>'), TokenType::String],
                $cursor->in('</') => [$cursor->take(1), null],
                default => [$cursor->until(Cursor::SPACE, '"\'=<>/'), TokenType::AttributeName],
            });
        }

        $end = $cursor->at('/>') ? $cursor->take(2) : $cursor->take(1);
        $output->add($end, TokenType::Punctuation);
        $language = $this->embedded[strtolower($name)] ?? null;

        if (!$closing && '>' === $end && null !== $language) {
            $found = stripos($cursor->text, '</'.$name, $cursor->position);
            $output->append($language->tokenize($cursor->take(false === $found ? \strlen($cursor->text) : $found - $cursor->position)));
        }
    }
}
