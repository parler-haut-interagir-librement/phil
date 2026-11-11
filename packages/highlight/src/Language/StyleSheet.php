<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\TokenType;

/**
 * CSS, and SCSS with its variables, line comments and interpolations. Each statement is a selector, a declaration or
 * an at-rule: at the top level a statement is a selector; inside a block it is a nested selector when a `{` comes
 * before the next `;` or `}`, and a declaration otherwise. The address in `url()` is read as a string, quoted or not.
 */
final readonly class StyleSheet implements Language
{
    private const int START = 0;

    private const int SELECTOR = 1;

    private const int PROPERTY = 2;

    private const int VALUE = 3;

    private const string NAME = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_';

    private const string UNIT = '0123456789.abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ%';

    public function __construct(
        private bool $scss = false,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->scss ? 'scss' : 'css';
    }

    #[Override]
    public function aliases(): array
    {
        return $this->scss ? ['less', 'sass'] : [];
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();
        $mode = self::START;
        $depth = 0;

        while (!$cursor->done()) {
            [$text, $type] = match (true) {
                $cursor->in(Cursor::SPACE) => [$cursor->span(Cursor::SPACE), null],
                $cursor->at('/*') => [$cursor->through('*/'), TokenType::Comment],
                $this->scss && $cursor->at('//') => [$cursor->line(), TokenType::Comment],
                $cursor->in('"\'') => [$cursor->quoted(), TokenType::String],
                $this->scss && $cursor->at('#{') => [$cursor->through('}'), TokenType::Variable],
                $this->scss && $cursor->at('$') => [$cursor->lead(self::NAME), TokenType::Variable],
                $cursor->at('{') => [$cursor->take(1), TokenType::Punctuation],
                $cursor->in(';}') => [$cursor->take(1), TokenType::Punctuation],
                self::START === $mode && $cursor->at('@') => [$cursor->lead(self::NAME), TokenType::Keyword],
                self::START === $mode => ['', null],
                self::SELECTOR === $mode => $this->selector($cursor),
                self::PROPERTY === $mode => $this->property($cursor),
                default => $this->value($cursor),
            };

            $output->add($text, $type);
            [$mode, $depth] = match (true) {
                '{' === $text => [self::START, $depth + 1],
                '}' === $text => [self::START, max(0, $depth - 1)],
                ';' === $text => [self::START, $depth],
                TokenType::Keyword === $type && '@' === $text[0] => [self::VALUE, $depth],
                TokenType::Variable === $type && '$' === $text[0] && self::START === $mode => [self::PROPERTY, $depth],
                ':' === $text && self::PROPERTY === $mode => [self::VALUE, $depth],
                self::START === $mode && '' === $text => [$this->statement($cursor, $depth), $depth],
                default => [$mode, $depth],
            };
        }

        return $output->tokens();
    }

    /**
     * What the statement at the position is: a selector at the top level or before a block, a declaration otherwise.
     */
    private function statement(Cursor $cursor, int $depth): int
    {
        return 0 === $depth || '{' === $cursor->char(strcspn($cursor->text, ';{}', $cursor->position)) ? self::SELECTOR : self::PROPERTY;
    }

    /**
     * @return array{string, ?TokenType}
     */
    private function selector(Cursor $cursor): array
    {
        return match (true) {
            $cursor->at(',') => [$cursor->take(1), TokenType::Punctuation],
            $cursor->in('>+~') => [$cursor->take(1), TokenType::Operator],
            $cursor->at('/') => [$cursor->take(1), null],
            default => [$this->selectorText($cursor), TokenType::Selector],
        };
    }

    /**
     * The text of a selector, up to an SCSS interpolation.
     */
    private function selectorText(Cursor $cursor): string
    {
        $name = $cursor->until(Cursor::SPACE, ',>+~{};"\'/');

        if ($this->scss && str_ends_with($name, '#') && $cursor->at('{')) {
            --$cursor->position;

            return substr($name, 0, -1);
        }

        return $name;
    }

    /**
     * @return array{string, ?TokenType}
     */
    private function property(Cursor $cursor): array
    {
        if ($cursor->at(':')) {
            return [$cursor->take(1), TokenType::Punctuation];
        }

        $name = $cursor->span(self::NAME);

        return '' === $name ? [$cursor->take(1), null] : [$name, str_starts_with($name, '--') ? TokenType::Variable : TokenType::Property];
    }

    /**
     * @return array{string, ?TokenType}
     */
    private function value(Cursor $cursor): array
    {
        if (0 === substr_compare($cursor->text, 'url(', $cursor->position - 4, 4, true) && !$cursor->in('"\')')) {
            return [$cursor->until(')'), TokenType::String];
        }

        if ($cursor->in(Cursor::DIGITS) || $cursor->in('.-') && $cursor->in(Cursor::DIGITS, 1)) {
            return [$cursor->lead(self::UNIT), TokenType::Number];
        }

        $name = $cursor->span(self::NAME);

        return match (true) {
            '' !== $name => [$name, match (true) {
                str_starts_with($name, '--') => TokenType::Variable,
                $cursor->at('(') => TokenType::Function,
                default => null,
            }],
            $cursor->at('#') => [$cursor->lead(self::NAME), TokenType::Number],
            $cursor->at('!') => [$cursor->lead(self::NAME), TokenType::Keyword],
            $cursor->in('(),:') => [$cursor->take(1), TokenType::Punctuation],
            $cursor->in('/*+=<>') => [$cursor->take(1), TokenType::Operator],
            default => [$cursor->take(1), null],
        };
    }
}
