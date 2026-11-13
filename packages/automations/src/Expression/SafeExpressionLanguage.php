<?php

declare(strict_types=1);

namespace Phil\Automations\Expression;

use Override;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Symfony's ExpressionLanguage without a single function: no `constant()`, no `enum()`, nothing that reaches past the
 * variables a condition is given. The operators stay: comparisons, `and`, `or`, `not`, `in`, `starts with`, `ends with`,
 * `contains`, `matches`, arithmetic, `??` and the array access `payload['plan']`.
 */
final class SafeExpressionLanguage extends ExpressionLanguage
{
    #[Override]
    protected function registerFunctions(): void
    {
    }
}
