<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

use Phil\LatexMathMl\Exception\InvalidLatex;

/**
 * The macros of a formula — `\newcommand`, `\renewcommand` and `\def`, with up to nine undelimited arguments —
 * expanded before the formula is read. Definitions are taken out of the tokens; each use is replaced by its body, its
 * arguments put in place of `#1` … `#9`.
 *
 * The expansion is bounded three ways: the number of uses expanded, the nesting of a use inside another's body, and
 * the tokens of the expanded formula. A macro used inside its own expansion is refused with the position of the use:
 * the formula would never end.
 *
 * @internal
 */
final class Macros
{
    /** The commands a macro may not take the name of: the parser needs them as they are. */
    private const array RESERVED = ['begin', 'end', 'left', 'right', 'middle', 'newcommand', 'renewcommand', 'def'];

    /** @var array<string, array{int, list<Token>}> the arity and the body of each macro, by name */
    private array $macros = [];

    private int $expansions = 0;

    private int $size = 0;

    /**
     * @param int $maxDepth      the deepest nesting of a use inside another's body
     * @param int $maxExpansions the most uses expanded in a formula
     * @param int $maxTokens     the most tokens of the expanded formula
     */
    public function __construct(
        private readonly int $maxDepth,
        private readonly int $maxExpansions,
        private readonly int $maxTokens,
    ) {
    }

    /**
     * The tokens with every definition taken out and every use expanded.
     *
     * @param list<Token> $tokens the tokens, ended by one of type `end`
     *
     * @return list<Token> the tokens, ended by the same `end`
     *
     * @throws InvalidLatex
     */
    public function expand(array $tokens): array
    {
        $end = array_pop($tokens) ?? new Token(Token::END, '', 0, '');
        $this->size = \count($tokens);
        $expanded = $this->expandList($tokens, [], $end);
        $expanded[] = $end;

        return $expanded;
    }

    /**
     * @param array<int, Token>   $tokens
     * @param array<string, true> $active the macros whose body is being expanded
     *
     * @return list<Token>
     */
    private function expandList(array $tokens, array $active, Token $end): array
    {
        $expanded = [];
        $position = 0;
        $count = \count($tokens);

        while ($position < $count) {
            $token = $tokens[$position];

            if ($token->is(Token::COMMAND, 'newcommand') || $token->is(Token::COMMAND, 'renewcommand')) {
                $position = $this->newCommand($tokens, $position, $end);
            } elseif ($token->is(Token::COMMAND, 'def')) {
                $position = $this->def($tokens, $position, $end);
            } elseif ($token->is(Token::COMMAND) && isset($this->macros[$token->value])) {
                [$body, $position] = $this->use($tokens, $position, $active, $end);
                array_push($expanded, ...$this->expandList($body, $active + [$token->value => true], $end));
            } else {
                $expanded[] = $token;
                ++$position;
            }
        }

        return $expanded;
    }

    /**
     * The body of the use at `$position`, its arguments in place, and the position after its last argument.
     *
     * @param array<int, Token>   $tokens
     * @param array<string, true> $active
     *
     * @return array{list<Token>, int}
     */
    private function use(array &$tokens, int $position, array $active, Token $end): array
    {
        $use = $tokens[$position];

        if (isset($active[$use->value])) {
            throw InvalidLatex::at($use->offset, \sprintf('"\%s" is used inside its own expansion', $use->value));
        }

        if (\count($active) >= $this->maxDepth) {
            throw InvalidLatex::at($use->offset, \sprintf('the macros nest deeper than %d levels', $this->maxDepth));
        }

        if (++$this->expansions > $this->maxExpansions) {
            throw InvalidLatex::at($use->offset, \sprintf('the formula expands more than %d macros', $this->maxExpansions));
        }

        [$arity, $body] = $this->macros[$use->value];
        $arguments = [];
        ++$position;

        for ($index = 1; $index <= $arity; ++$index) {
            [$argument, $position] = $this->argument($tokens, $position, $use, $end);
            $arguments[$index] = $this->expandList($argument, $active, $end);
        }

        $substituted = [];

        foreach ($body as $at => $token) {
            if ($token->is(Token::CHAR, '#')) {
                continue;
            }

            if ($token->is(Token::NUMBER) && $at > 0 && $body[$at - 1]->is(Token::CHAR, '#')) {
                $this->grow(\count($arguments[(int) $token->value[0]]), $use);
                array_push($substituted, ...$arguments[(int) $token->value[0]]);

                if (\strlen($token->value) > 1) {
                    $substituted[] = new Token(Token::NUMBER, substr($token->value, 1), $use->offset, substr($token->raw, 1));
                }

                continue;
            }

            $this->grow(1, $use);
            $substituted[] = new Token($token->type, $token->value, $use->offset, $token->raw);
        }

        return [$substituted, $position];
    }

    /**
     * `\newcommand{\name}[n]{body}` or `\renewcommand`, the braces around the name optional, and the position after it.
     *
     * @param array<int, Token> $tokens
     */
    private function newCommand(array $tokens, int $position, Token $end): int
    {
        $command = $tokens[$position];
        $position = $this->solid($tokens, $position + 1);

        if ($this->at($tokens, $position, $end)->is(Token::CHAR, '*')) {
            $position = $this->solid($tokens, $position + 1);
        }

        $braced = $this->at($tokens, $position, $end)->is(Token::OPEN);
        $position = $braced ? $this->solid($tokens, $position + 1) : $position;
        $name = $this->name($this->at($tokens, $position, $end), $command);
        $position = $this->solid($tokens, $position + 1);

        if ($braced) {
            if (!$this->at($tokens, $position, $end)->is(Token::CLOSE)) {
                throw InvalidLatex::at($command->offset, \sprintf('"%s" names one command between braces', $command->raw));
            }

            $position = $this->solid($tokens, $position + 1);
        }

        if ('newcommand' === $command->value && isset($this->macros[$name])) {
            throw InvalidLatex::at($command->offset, \sprintf('"\%s" is already defined; redefine it with "\renewcommand"', $name));
        }

        $arity = 0;

        if ($this->at($tokens, $position, $end)->is(Token::CHAR, '[')) {
            $number = $this->at($tokens, $this->solid($tokens, $position + 1), $end);
            $close = $this->solid($tokens, $this->solid($tokens, $position + 1) + 1);

            if (1 !== preg_match('/^[0-9]$/', $number->value) || !$this->at($tokens, $close, $end)->is(Token::CHAR, ']')) {
                throw InvalidLatex::at($number->offset, 'the number of arguments of a macro is a digit between brackets, "[2]"');
            }

            $arity = (int) $number->value;
            $position = $this->solid($tokens, $close + 1);
        }

        if ($this->at($tokens, $position, $end)->is(Token::CHAR, '[')) {
            throw InvalidLatex::at($this->at($tokens, $position, $end)->offset, 'a macro with an optional argument is not supported');
        }

        return $this->define($name, $arity, $tokens, $position, $command, $end);
    }

    /**
     * `\def\name#1#2{body}`, its parameters undelimited and in order, and the position after it.
     *
     * @param array<int, Token> $tokens
     */
    private function def(array $tokens, int $position, Token $end): int
    {
        $command = $tokens[$position];
        $position = $this->solid($tokens, $position + 1);
        $name = $this->name($this->at($tokens, $position, $end), $command);
        $position = $this->solid($tokens, $position + 1);
        $arity = 0;

        while ($this->at($tokens, $position, $end)->is(Token::CHAR, '#')) {
            $number = $this->at($tokens, $position + 1, $end);

            if ((string) ($arity + 1) !== $number->value) {
                throw InvalidLatex::at($number->offset, \sprintf('the parameters of "\def" are #1 to #9, in order and without delimiters; #%d comes next', $arity + 1));
            }

            ++$arity;
            $position = $this->solid($tokens, $position + 2);
        }

        return $this->define($name, $arity, $tokens, $position, $command, $end);
    }

    /**
     * Reads the body of a macro, a group whose `#` are each followed by the number of an argument, keeps it, and
     * returns the position after it.
     *
     * @param array<int, Token> $tokens
     */
    private function define(string $name, int $arity, array $tokens, int $position, Token $command, Token $end): int
    {
        if (!$this->at($tokens, $position, $end)->is(Token::OPEN)) {
            throw InvalidLatex::at($this->at($tokens, $position, $end)->offset, \sprintf('the body of "\%s" is a group between braces', $name));
        }

        [$body, $after] = $this->argument($tokens, $position, $command, $end);

        foreach ($body as $at => $token) {
            if (!$token->is(Token::CHAR, '#')) {
                continue;
            }

            $number = $body[$at + 1] ?? $end;
            $digit = $number->is(Token::NUMBER) ? (int) $number->value[0] : 0;

            if ($digit < 1 || $digit > $arity) {
                throw InvalidLatex::at($token->offset, \sprintf('"#" in the body of "\%s" is followed by the number of one of its %d arguments', $name, $arity));
            }
        }

        $this->macros[$name] = [$arity, $body];

        return $after;
    }

    private function name(Token $token, Token $command): string
    {
        if (!$token->is(Token::COMMAND) || 1 !== preg_match('/^[A-Za-z]+$/', $token->value) || \in_array($token->value, self::RESERVED, true)) {
            throw InvalidLatex::at($token->offset, \sprintf('"%s" needs the name of a command made of letters, other than \begin, \end, \left, \right, \middle and the definitions', $command->raw));
        }

        return $token->value;
    }

    /**
     * An argument: the tokens of a group, its braces left out, or one token — one digit of a number, the others left
     * in its place for what follows —, and the position after it.
     *
     * @param array<int, Token> $tokens
     *
     * @return array{list<Token>, int}
     */
    private function argument(array &$tokens, int $position, Token $command, Token $end): array
    {
        $position = $this->solid($tokens, $position);
        $token = $this->at($tokens, $position, $end);

        if ($token->is(Token::END) || $token->is(Token::CLOSE)) {
            throw InvalidLatex::at($command->offset, \sprintf('"%s" needs an argument', $command->raw));
        }

        if ($token->is(Token::NUMBER) && \strlen($token->value) > 1) {
            $tokens[$position] = new Token(Token::NUMBER, substr($token->value, 1), $token->offset + 1, substr($token->raw, 1));

            return [[new Token(Token::NUMBER, $token->value[0], $token->offset, $token->raw[0])], $position];
        }

        if (!$token->is(Token::OPEN)) {
            return [[$token], $position + 1];
        }

        $level = 0;
        $start = $position + 1;

        for ($index = $start, $count = \count($tokens); $index < $count; ++$index) {
            $level += $tokens[$index]->is(Token::OPEN) ? 1 : 0;

            if ($tokens[$index]->is(Token::CLOSE) && 0 === $level--) {
                return [\array_slice($tokens, $start, $index - $start), $index + 1];
            }
        }

        throw InvalidLatex::at($token->offset, 'this group is never closed');
    }

    /**
     * The position of the next token that is not a space.
     *
     * @param array<int, Token> $tokens
     */
    private function solid(array $tokens, int $position): int
    {
        while (isset($tokens[$position]) && $tokens[$position]->is(Token::SPACE)) {
            ++$position;
        }

        return $position;
    }

    /**
     * @param array<int, Token> $tokens
     */
    private function at(array $tokens, int $position, Token $end): Token
    {
        return $tokens[$position] ?? $end;
    }

    private function grow(int $tokens, Token $use): void
    {
        $this->size += $tokens;

        if ($this->size > $this->maxTokens) {
            throw InvalidLatex::at($use->offset, \sprintf('the formula expands to more than %d tokens', $this->maxTokens));
        }
    }
}
