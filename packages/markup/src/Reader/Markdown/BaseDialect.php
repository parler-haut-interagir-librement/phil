<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Override;
use Phil\Markup\Reader\Markdown\Contract\MarkdownDialect;

/**
 * A dialect that reads everything as plain Markdown does: a dialect extends it and overrides what it changes.
 */
abstract class BaseDialect implements MarkdownDialect
{
    #[Override]
    public function inlineRules(): array
    {
        return [];
    }

    #[Override]
    public function blockRules(): array
    {
        return [];
    }

    #[Override]
    public function disables(): array
    {
        return [];
    }

    #[Override]
    public function frontMatter(array $lines): ?array
    {
        return null;
    }

    #[Override]
    public function define(string $label, string $value, Context $context): bool
    {
        return false;
    }

    #[Override]
    public function definition(string $line): ?string
    {
        return null;
    }

    #[Override]
    public function paragraph(string $text): array
    {
        return [$text, []];
    }
}
