<?php

declare(strict_types=1);

namespace Phil\TextDiff\Tests\Unit;

use Phil\TextDiff\Operation;
use Phil\TextDiff\OperationKind;

/**
 * Operations written short for exact expectations: `=` kept, `-` deleted, `+` inserted, tokens joined by `|`.
 */
final class Operations
{
    /**
     * @param list<Operation> $operations
     *
     * @return list<string>
     */
    public static function describe(array $operations): array
    {
        return array_map(static fn (Operation $operation): string => match ($operation->kind) {
            OperationKind::Equal => '=',
            OperationKind::Delete => '-',
            OperationKind::Insert => '+',
        }.implode('|', $operation->tokens), $operations);
    }
}
