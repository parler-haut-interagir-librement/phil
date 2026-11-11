<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Infrastructure\Language;

use Override;
use Phil\Highlight\Language;

/**
 * A language under more names: its own name, and the aliases of the configuration in place of its own aliases, which
 * the highlighter already holds. It reads code as the language does.
 */
final readonly class AliasedLanguage implements Language
{
    /**
     * @param list<string> $aliases
     */
    public function __construct(
        private Language $language,
        private array $aliases,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->language->name();
    }

    #[Override]
    public function aliases(): array
    {
        return $this->aliases;
    }

    #[Override]
    public function tokenize(string $code): array
    {
        return $this->language->tokenize($code);
    }
}
