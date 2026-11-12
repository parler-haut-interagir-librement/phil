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
 * The document, or else every operation, declares a `security` requirement; an empty one says the operation is public.
 *
 * A client needs to know which credentials an operation takes. An operation that is public on purpose says so with
 * `security: []`, so that a forgotten requirement does not pass for a public endpoint.
 */
final readonly class SecurityRequirement implements OpenApiRule
{
    public const string ID = 'security-requirement';

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
        return 'The document, or else every operation, declares a `security` requirement; an empty one says the operation is public.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        if (\is_array($document->data['security'] ?? null)) {
            return;
        }

        foreach ($document->operations() as $operation) {
            if (!\is_array($operation->operation['security'] ?? null)) {
                yield Finding::of($this, $operation->pointer, \sprintf('%s %s declares no security requirement.', strtoupper($operation->method), $operation->path));
            }
        }
    }
}
