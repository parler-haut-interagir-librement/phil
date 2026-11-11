<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Unit;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Token;
use Phil\Highlight\TokenType;

/**
 * A language of one token: the whole code, a keyword.
 */
final readonly class ShoutLanguage implements Language
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        private string $name = 'shout',
        private array $aliases = ['yell'],
    ) {
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
        return [new Token($code, TokenType::Keyword)];
    }
}
