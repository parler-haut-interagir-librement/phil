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
 * Every operation declares a `4XX` response or a `default` one.
 *
 * A client handles the errors it knows of; an operation that documents only its successes leaves it to discover them
 * in production.
 */
final readonly class ErrorResponses implements OpenApiRule
{
    public const string ID = 'error-responses';

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
        return 'Every operation declares a `4XX` response or a `default` one.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            $responses = $operation->operation['responses'] ?? null;
            $codes = \is_array($responses) ? array_keys($responses) : [];

            if ([] === preg_grep('/^(?:4(?:\d\d|XX)|default)$/iD', $codes)) {
                yield Finding::of($this, $operation->pointer.'/responses', \sprintf('%s %s declares no error response.', strtoupper($operation->method), $operation->path));
            }
        }
    }
}
