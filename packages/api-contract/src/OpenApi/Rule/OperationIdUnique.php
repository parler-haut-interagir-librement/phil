<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi\Rule;

use Override;
use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\OpenApi\Finding;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;

/**
 * No two operations share an `operationId`.
 *
 * Generated clients name their methods after the operation ids: two operations with the same id cannot both be
 * called.
 */
final readonly class OperationIdUnique implements OpenApiRule
{
    public const string ID = 'operation-id-unique';

    #[Override]
    public function id(): string
    {
        return self::ID;
    }

    #[Override]
    public function level(): RuleLevel
    {
        return RuleLevel::Minimal;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Error;
    }

    #[Override]
    public function description(): string
    {
        return 'No two operations share an `operationId`.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        $seen = [];

        foreach ($document->operations() as $operation) {
            $id = $operation->operation['operationId'] ?? null;

            if (!\is_string($id)) {
                continue;
            }

            if (isset($seen[$id])) {
                yield Finding::of($this, $operation->pointer.'/operationId', \sprintf('The operationId "%s" is already used at %s.', $id, $seen[$id]));
            } else {
                $seen[$id] = $operation->pointer;
            }
        }
    }
}
