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
 * Every operation has a non-empty `responses` object.
 *
 * An operation without responses tells the client nothing of what comes back.
 */
final readonly class OperationResponses implements OpenApiRule
{
    public const string ID = 'operation-responses';

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
        return 'Every operation has a non-empty `responses` object.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            $responses = $operation->operation['responses'] ?? null;

            if (!\is_array($responses) || [] === $responses) {
                yield Finding::of($this, $operation->pointer.'/responses', \sprintf('%s %s declares no response.', strtoupper($operation->method), $operation->path));
            }
        }
    }
}
