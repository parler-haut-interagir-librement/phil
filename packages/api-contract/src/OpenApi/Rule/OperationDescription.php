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
 * Every operation has a non-empty `summary` or `description`.
 *
 * The reader of the document learns what an operation does from its summary, not from its path.
 */
final readonly class OperationDescription implements OpenApiRule
{
    public const string ID = 'operation-description';

    #[Override]
    public function id(): string
    {
        return self::ID;
    }

    #[Override]
    public function level(): RuleLevel
    {
        return RuleLevel::Strict;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Warning;
    }

    #[Override]
    public function description(): string
    {
        return 'Every operation has a non-empty `summary` or `description`.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            foreach (['summary', 'description'] as $field) {
                $text = $operation->operation[$field] ?? null;

                if (\is_string($text) && '' !== trim($text)) {
                    continue 2;
                }
            }

            yield Finding::of($this, $operation->pointer, \sprintf('%s %s has neither summary nor description.', strtoupper($operation->method), $operation->path));
        }
    }
}
