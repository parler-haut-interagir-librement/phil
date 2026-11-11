<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\TokenType;

/**
 * Unified and normal diffs and patches, line by line: the headers of files, the headers of hunks, the lines added and
 * removed, and the notes that open with a backslash.
 */
final readonly class Diff implements Language
{
    private const array HEADERS = ['diff ', 'index ', 'Index: ', '--- ', '+++ ', '*** ', '==='];

    #[Override]
    public function name(): string
    {
        return 'diff';
    }

    #[Override]
    public function aliases(): array
    {
        return ['patch', 'udiff'];
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();

        while (!$cursor->done()) {
            $type = match (true) {
                array_any(self::HEADERS, $cursor->at(...)) => TokenType::Meta,
                $cursor->at('@@') => TokenType::Section,
                $cursor->in('+>') => TokenType::Inserted,
                $cursor->in('-<') => TokenType::Deleted,
                $cursor->at('\\') => TokenType::Comment,
                default => null,
            };

            $output->add($cursor->line(), $type);
            $output->add($cursor->take(1));
        }

        return $output->tokens();
    }
}
