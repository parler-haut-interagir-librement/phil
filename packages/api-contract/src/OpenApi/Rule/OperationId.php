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
 * Every operation has a non-empty `operationId`.
 *
 * The operation id names the endpoint in generated clients, in logs and in the record of observed deprecations,
 * instead of a method and a path that may change.
 */
final readonly class OperationId implements OpenApiRule
{
    public const string ID = 'operation-id';

    #[Override]
    public function id(): string
    {
        return self::ID;
    }

    #[Override]
    public function level(): RuleLevel
    {
        return RuleLevel::Recommended;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Warning;
    }

    #[Override]
    public function description(): string
    {
        return 'Every operation has a non-empty `operationId`.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            $id = $operation->operation['operationId'] ?? null;

            if (!\is_string($id) || '' === trim($id)) {
                yield Finding::of($this, $operation->pointer, \sprintf('%s %s has no operationId.', strtoupper($operation->method), $operation->path));
            }
        }
    }
}
