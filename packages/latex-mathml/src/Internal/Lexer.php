<?php

declare(strict_types=1);

namespace Phil\LatexMathMl\Internal;

use Phil\LatexMathMl\Exception\InvalidLatex;

/**
 * The tokens of a formula, with the comments after `%` left out and `~` read as a space command.
 *
 * @internal
 */
final class Lexer
{
    private const string PATTERN = '/\\\\(?<command>[A-Za-z]+|[^A-Za-z])|(?<comment>%[^\n]*)|(?<number>[0-9]+(?:\.[0-9]+)?)|(?<letter>[A-Za-z]|\p{L})|(?<space>\s+)|(?<char>.)/suA';

    /**
     * @return list<Token>
     *
     * @throws InvalidLatex when the formula is not UTF-8
     */
    public static function tokens(string $latex): array
    {
        if (!mb_check_encoding($latex, 'UTF-8')) {
            throw InvalidLatex::at(0, 'the formula is not valid UTF-8');
        }

        $tokens = [];
        $offset = 0;
        $length = \strlen($latex);

        while ($offset < $length) {
            // Every character matches one of the groups, a lone backslash the last.
            preg_match(self::PATTERN, $latex, $match, \PREG_UNMATCHED_AS_NULL, $offset);
            $raw = $match[0] ?? '';
            $command = $match['command'] ?? null;
            $token = match (true) {
                null !== $command => new Token(Token::COMMAND, $command, $offset, $raw),
                null !== ($match['comment'] ?? null) => null,
                null !== ($match['number'] ?? null) => new Token(Token::NUMBER, $raw, $offset, $raw),
                null !== ($match['letter'] ?? null) => new Token(Token::LETTER, $raw, $offset, $raw),
                null !== ($match['space'] ?? null) => new Token(Token::SPACE, ' ', $offset, $raw),
                '~' === $raw => new Token(Token::COMMAND, ' ', $offset, $raw),
                \in_array($raw, [Token::OPEN, Token::CLOSE, Token::SUP, Token::SUB, Token::ALIGN], true) => new Token($raw, $raw, $offset, $raw),
                default => new Token(Token::CHAR, $raw, $offset, $raw),
            };

            if ($token instanceof Token) {
                $tokens[] = $token;
            }

            $offset += \strlen($raw);
        }

        $tokens[] = new Token(Token::END, '', $length, '');

        return $tokens;
    }
}
