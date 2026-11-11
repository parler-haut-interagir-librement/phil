<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;

/**
 * The tokenizer of a programming language described by a `Syntax`. It reads the source once, from left to right, and
 * at each position tries, in order: spaces, meta marks, a preprocessor line, comments, quoted texts, variables,
 * annotations, symbols, numbers, words, regular expressions, operators and punctuation. A word is a keyword, a constant,
 * a type or a built-in by its lists — in that order of precedence —, else a call when `(` follows, else a type when the
 * syntax says capitals make types.
 */
final readonly class Code implements Language
{
    private const string ALNUM = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_';

    /** @var array<string, TokenType> */
    private array $words;

    private string $word;

    public function __construct(
        private Syntax $syntax,
    ) {
        $words = [];

        foreach ([[$syntax->builtins, TokenType::Builtin], [$syntax->types, TokenType::Type], [$syntax->constants, TokenType::Constant], [$syntax->keywords, TokenType::Keyword]] as [$list, $type]) {
            foreach ($list as $word) {
                $words[$syntax->caseInsensitive ? strtolower($word) : $word] = $type;
            }
        }

        $this->words = $words;
        $this->word = implode('', [Cursor::WORD, $syntax->wordChars]);
    }

    #[Override]
    public function name(): string
    {
        return $this->syntax->name;
    }

    #[Override]
    public function aliases(): array
    {
        return $this->syntax->aliases;
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();

        if ($this->syntax->shebang && $cursor->at('#!')) {
            $output->add($cursor->line(), TokenType::Meta);
        }

        while (!$cursor->done()) {
            $this->next($cursor, $output);
        }

        return $output->tokens();
    }

    private function next(Cursor $cursor, Output $output): void
    {
        $syntax = $this->syntax;

        $output->add(...match (true) {
            null !== $mark = $this->mark($cursor) => [$cursor->take(\strlen($mark)), TokenType::Meta],
            $syntax->preprocessor && $cursor->at('#') && $cursor->atLineStart() => [$this->directive($cursor), TokenType::Meta],
            null !== $comment = $this->comment($cursor) => [$comment, TokenType::Comment],
            null !== $string = $this->string($cursor) => [$string, $syntax->propertyKeys && ':' === $this->following($cursor) ? TokenType::Property : TokenType::String],
            $cursor->in($syntax->variablePrefixes) => $this->variable($cursor),
            '' !== $syntax->annotation && $cursor->at($syntax->annotation) && 1 === strspn($cursor->text, $this->word, $cursor->position + \strlen($syntax->annotation), 1) => [$cursor->take(\strlen($syntax->annotation)).$cursor->span($this->word, '.'), TokenType::Meta],
            $syntax->symbols && $cursor->at(':') && $cursor->in($this->word, 1) && 0 === strspn($cursor->previous(), $this->word) => [$cursor->lead($this->word), TokenType::Constant],
            $cursor->in(Cursor::DIGITS) || $cursor->at('.') && $cursor->in(Cursor::DIGITS, 1) && '.' !== $cursor->previous() => [$this->number($cursor), TokenType::Number],
            $cursor->in($this->word) => $this->word($cursor),
            $syntax->regex && $cursor->at('/') && $this->expectsValue($output->significant()) => $this->regex($cursor),
            $cursor->in($syntax->operators) => [$cursor->span($syntax->operators), TokenType::Operator],
            $cursor->in($syntax->punctuation) => [$cursor->take(1), TokenType::Punctuation],
            default => [$cursor->take(1), null],
        });
    }

    /**
     * The meta mark at the position, or `null`.
     */
    private function mark(Cursor $cursor): ?string
    {
        return array_find($this->syntax->metaMarks, $cursor->at(...));
    }

    /**
     * A preprocessor line, continued by a backslash at its end.
     */
    private function directive(Cursor $cursor): string
    {
        $text = $cursor->line();

        while (str_ends_with($text, '\\') && $cursor->at("\n")) {
            $text .= $cursor->nextLine();
        }

        return $text;
    }

    /**
     * A comment at the position: a block comment first, so `--[[` of Lua opens a block before `--` opens a line.
     */
    private function comment(Cursor $cursor): ?string
    {
        foreach ($this->syntax->blockComments as $open => $close) {
            if ($cursor->at($open)) {
                return $cursor->take(\strlen($open)).$cursor->through($close);
            }
        }

        foreach ($this->syntax->lineComments as $mark) {
            if ($cursor->at($mark) && (!$this->syntax->commentAfterSpace || str_contains(Cursor::SPACE, $cursor->previous()))) {
                return $cursor->line();
            }
        }

        return null;
    }

    /**
     * A quoted text at the position, with its prefix; `null` when none opens there, or when a character literal does not
     * close after its one character.
     */
    private function string(Cursor $cursor): ?string
    {
        $text = $cursor->text;
        $start = $cursor->position + strspn($text, $this->syntax->stringPrefixes, $cursor->position, 3);

        foreach ($this->syntax->quotes as $quote) {
            if (substr($text, $start, \strlen($quote->open)) !== $quote->open) {
                continue;
            }

            $at = $start + \strlen($quote->open);

            if (!$quote->character || ($text[$at] ?? '') === $quote->escape) {
                return $cursor->take($this->closing($text, $at, $quote) - $cursor->position);
            }

            $at += $this->characterLength(\ord(($text[$at] ?? "\0")[0]));

            return substr($text, $at, \strlen($quote->close)) === $quote->close ? $cursor->take($at + \strlen($quote->close) - $cursor->position) : null;
        }

        return null;
    }

    /**
     * The bytes of the UTF-8 character whose first byte is `$byte`.
     */
    private function characterLength(int $byte): int
    {
        return match (true) {
            $byte < 0xC0 => 1,
            $byte < 0xE0 => 2,
            $byte < 0xF0 => 3,
            default => 4,
        };
    }

    /**
     * Where a quoted text that opened before `$at` ends: after its closing mark, at the line feed of a text that cannot
     * span lines, or at the end.
     */
    private function closing(string $text, int $at, Quote $quote): int
    {
        $stops = implode('', [$quote->close[0], $quote->escape, $quote->multiline ? '' : "\n"]);
        $close = \strlen($quote->close);

        while (true) {
            $at += strcspn($text, $stops, $at);
            $char = $text[$at] ?? "\n";

            if ("\n" === $char) {
                return min($at, \strlen($text));
            }

            if ($char === $quote->escape) {
                $at += 2;

                continue;
            }

            if (substr($text, $at, $close) === $quote->close) {
                return $at + $close;
            }

            ++$at;
        }
    }

    /**
     * The first character after the position that is not a space or a tab.
     */
    private function following(Cursor $cursor): string
    {
        return $cursor->char(strspn($cursor->text, " \t", $cursor->position));
    }

    /**
     * @return array{string, TokenType}
     */
    private function variable(Cursor $cursor): array
    {
        $sigil = $cursor->span($this->syntax->variablePrefixes);
        $name = match (true) {
            $cursor->at('{') => $cursor->through('}'),
            $cursor->in($this->word) => $cursor->span($this->word),
            default => $cursor->in($this->syntax->specialVariables) ? $cursor->take(1) : '',
        };

        return [$sigil.$name, '' === $name && 1 === \strlen($sigil) ? TokenType::Operator : TokenType::Variable];
    }

    /**
     * A number: digits and letters — `0x1F`, `1_000u32` —, a fraction, and the sign of an exponent.
     */
    private function number(Cursor $cursor): string
    {
        $number = $cursor->span(self::ALNUM);

        if ($cursor->at('.') && $cursor->in(Cursor::DIGITS, 1)) {
            $number .= $cursor->lead(self::ALNUM);
        }

        if ($cursor->in('+-') && str_ends_with(strtolower($number), 'e') && !str_starts_with(strtolower($number), '0x')) {
            $number .= $cursor->lead(self::ALNUM);
        }

        return $number;
    }

    /**
     * @return array{string, ?TokenType}
     */
    private function word(Cursor $cursor): array
    {
        $called = 1 === strspn($cursor->previous(), $this->syntax->callAfter);
        $word = $cursor->span($this->word);
        $word .= $cursor->in($this->syntax->wordSuffixes) && !$cursor->in('=', 1) ? $cursor->take(1) : '';
        $known = $this->words[$this->syntax->caseInsensitive ? strtolower($word) : $word] ?? null;

        if (null !== $known) {
            return [$word, $known];
        }

        if ($this->syntax->macros && $cursor->at('!') && '=' !== $cursor->char(1)) {
            return [$word.$cursor->take(1), TokenType::Function];
        }

        return [$word, match (true) {
            $called, '(' === $this->following($cursor) => TokenType::Function,
            $this->syntax->capitalizedTypes && 1 === strspn($word, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 0, 1) => TokenType::Type,
            default => null,
        }];
    }

    /**
     * Whether a value may stand after the last token — so a `/` opens a regular expression rather than divides.
     */
    private function expectsValue(?Token $last): bool
    {
        return !$last instanceof Token
            || \in_array($last->type, [TokenType::Keyword, TokenType::Operator], true)
            || TokenType::Punctuation === $last->type && !str_contains(')]}', $last->text);
    }

    /**
     * A regular expression with its flags; when its line ends before it closes, a lone `/` operator.
     *
     * @return array{string, TokenType}
     */
    private function regex(Cursor $cursor): array
    {
        $text = $cursor->text;
        $at = $cursor->position + 1;
        $class = false;

        while (true) {
            $at += strcspn($text, "/\\[]\n", $at);
            $char = $text[$at] ?? "\n";

            if ("\n" === $char) {
                return [$cursor->take(1), TokenType::Operator];
            }

            if ('/' === $char && !$class) {
                break;
            }

            $class = '[' === $char || $class && ']' !== $char;
            $at += '\\' === $char ? 2 : 1;
        }

        ++$at;

        return [$cursor->take($at - $cursor->position + strspn($text, 'abcdefghijklmnopqrstuvwxyz', $at)), TokenType::Regex];
    }
}
