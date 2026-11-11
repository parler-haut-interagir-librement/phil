<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;
use Phil\Highlight\TokenType;

/**
 * Twig templates: comments, the delimiters of prints and tags with their whitespace controls, the expressions between
 * them read by the expression language given, and the text between read by the markup language given. Each stretch of
 * markup is read on its own, so a tag cut by a Twig expression — `<a href="{{ url }}">` — loses some colours after the
 * cut.
 */
final readonly class Twig implements Language
{
    public function __construct(
        private Language $markup,
        private Language $expression,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return 'twig';
    }

    #[Override]
    public function aliases(): array
    {
        return ['html.twig', 'jinja', 'jinja2', 'nunjucks', 'njk', 'liquid'];
    }

    #[Override]
    public function tokenize(string $code): array
    {
        $cursor = new Cursor($code);
        $output = new Output();

        while (!$cursor->done()) {
            $at = $cursor->position;

            while (false !== $at = strpos($code, '{', $at)) {
                if (1 === strspn($code, '{%#', $at + 1, 1)) {
                    break;
                }

                ++$at;
            }

            $markup = $cursor->take((false === $at ? \strlen($code) : $at) - $cursor->position);
            $output->append($this->markup->tokenize($markup));

            if ($cursor->at('{#')) {
                $output->add($cursor->through('#}'), TokenType::Comment);
            } elseif (!$cursor->done()) {
                $this->delimited($cursor, $output);
            }
        }

        return $output->tokens();
    }

    /**
     * A print or a tag: its opening delimiter, its expression, and its closing delimiter.
     */
    private function delimited(Cursor $cursor, Output $output): void
    {
        $close = $cursor->at('{{') ? '}}' : '%}';
        $output->add($cursor->take(2).$cursor->span('-~'), TokenType::Punctuation);
        $end = strpos($cursor->text, $close, $cursor->position);
        $inner = $cursor->take((false === $end ? \strlen($cursor->text) : $end) - $cursor->position);
        $expression = \in_array(substr($inner, -1), ['-', '~'], true) ? substr($inner, 0, -1) : $inner;
        $output->append($this->expression->tokenize($expression));
        $output->add(substr($inner, \strlen($expression)).$cursor->take(2), TokenType::Punctuation);
    }
}
