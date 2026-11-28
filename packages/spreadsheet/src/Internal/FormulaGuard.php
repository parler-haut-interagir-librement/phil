<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

/**
 * The texts a spreadsheet would run as a formula when the file is opened or pasted: those starting with `=`, `+`, `-`,
 * `@`, a tab or a carriage return (OWASP, CSV injection).
 *
 * @internal
 */
final class FormulaGuard
{
    public static function isRisky(string $text): bool
    {
        return '' !== $text && str_contains("=+-@\t\r", $text[0]);
    }
}
