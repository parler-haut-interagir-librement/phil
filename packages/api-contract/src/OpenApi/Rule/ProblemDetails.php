<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi\Rule;

use Override;
use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\OpenApi\Finding;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;
use Phil\ApiContract\Problem\ProblemDetails as ProblemDetailsDocument;

/**
 * Every error response (`4XX`, `5XX` or `default`) with a body offers it as `application/problem+json`.
 *
 * Errors are RFC 9457 problem details, which every client reads the same way, whatever the endpoint.
 */
final readonly class ProblemDetails implements OpenApiRule
{
    public const string ID = 'problem-details';

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
        return 'Every error response (`4XX`, `5XX` or `default`) with a body offers it as `application/problem+json`.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            foreach ($operation->responses($document) as $code => $response) {
                $content = $response['content'] ?? null;

                if (1 !== preg_match('/^(?:[45](?:\d\d|XX)|default)$/iD', (string) $code) || !\is_array($content) || [] === $content) {
                    continue;
                }

                $types = array_map(static fn (int|string $type): string => strtolower(trim(explode(';', (string) $type)[0])), array_keys($content));

                if (!\in_array(ProblemDetailsDocument::MEDIA_TYPE, $types, true)) {
                    yield Finding::of($this, $operation->pointer.OpenApiDocument::pointer('responses', $code, 'content'), \sprintf('The %s response of %s %s is not offered as %s.', $code, strtoupper($operation->method), $operation->path, ProblemDetailsDocument::MEDIA_TYPE));
                }
            }
        }
    }
}
