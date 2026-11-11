<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\TokenType;

/**
 * INI files, `.env` files and their kin, line by line: comments opened by `;` or `#`, `[sections]`, and `key = value`
 * or `key: value` pairs, a value being a quoted string, a number, a constant or a plain string; a comment may follow a
 * value after a space.
 */
final readonly class Ini implements Language
{
    private const array CONSTANTS = ['true', 'false', 'on', 'off', 'yes', 'no', 'null', 'none'];

    #[Override]
    public function name(): string
    {
        return 'ini';
    }

    #[Override]
    public function aliases(): array
    {
        return ['dosini', 'cfg', 'conf', 'properties', 'env', 'dotenv', 'editorconfig', 'gitconfig'];
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();

        while (!$cursor->done()) {
            $output->add($cursor->span(" \t"));
            $this->line($cursor, $output);
            $output->add($cursor->take(1));
        }

        return $output->tokens();
    }

    private function line(Cursor $cursor, Output $output): void
    {
        if ($cursor->in(';#')) {
            $output->add($cursor->line(), TokenType::Comment);
        } elseif ($cursor->at('[')) {
            $output->add($cursor->until("]\n").($cursor->at(']') ? $cursor->take(1) : ''), TokenType::Section);
            $this->value($cursor, $output);
        } elseif (1 === strspn($cursor->text, '=:', $cursor->position + strcspn($cursor->text, "=:\n", $cursor->position), 1)) {
            $key = $cursor->until('=:');
            $name = rtrim($key, " \t");
            $output->add($name, TokenType::Property);
            $output->add(substr($key, \strlen($name)));
            $output->add($cursor->take(1), TokenType::Operator);
            $this->value($cursor, $output);
        } else {
            $output->add($cursor->line());
        }
    }

    /**
     * The rest of a line: its value, and the comment after it.
     */
    private function value(Cursor $cursor, Output $output): void
    {
        $output->add($cursor->span(" \t"));
        $output->add($cursor->in('"\'') ? $cursor->quoted() : '', TokenType::String);

        $text = $cursor->untilComment("\n;#", ';#');
        $value = rtrim($text, " \t");
        $output->add($value, match (true) {
            \in_array(strtolower($value), self::CONSTANTS, true) => TokenType::Constant,
            is_numeric($value) => TokenType::Number,
            default => TokenType::String,
        });
        $output->add(substr($text, \strlen($value)));
        $output->add($cursor->line(), TokenType::Comment);
    }
}
