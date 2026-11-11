<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\TokenType;

/**
 * Dockerfiles: comments, the instruction that opens each line, and its arguments — continued by a backslash at the end
 * of a line — read by the shell language given.
 */
final readonly class Dockerfile implements Language
{
    private const array INSTRUCTIONS = ['ADD', 'ARG', 'CMD', 'COPY', 'ENTRYPOINT', 'ENV', 'EXPOSE', 'FROM', 'HEALTHCHECK', 'LABEL', 'MAINTAINER', 'ONBUILD', 'RUN', 'SHELL', 'STOPSIGNAL', 'USER', 'VOLUME', 'WORKDIR'];

    public function __construct(
        private Language $shell,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return 'dockerfile';
    }

    #[Override]
    public function aliases(): array
    {
        return ['docker', 'containerfile'];
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();

        while (!$cursor->done()) {
            $output->add($cursor->span(Cursor::SPACE));

            if ($cursor->at('#')) {
                $output->add($cursor->line(), TokenType::Comment);

                continue;
            }

            $word = $cursor->span(Cursor::WORD);
            $output->add($word, \in_array(strtoupper($word), self::INSTRUCTIONS, true) ? TokenType::Keyword : null);
            $arguments = $cursor->line();

            while (str_ends_with(rtrim($arguments, " \t"), '\\') && $cursor->at("\n")) {
                $arguments .= $cursor->nextLine();
            }

            $output->append($this->shell->tokenize($arguments));
        }

        return $output->tokens();
    }
}
