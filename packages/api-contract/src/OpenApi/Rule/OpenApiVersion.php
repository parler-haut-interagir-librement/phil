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
 * The document declares OpenAPI 3.0, 3.1 or 3.2 in its `openapi` field.
 *
 * The `openapi` field names the version of the specification the document follows. A Swagger 2.0 document, or one
 * without the field, is read by no tool the same way.
 */
final readonly class OpenApiVersion implements OpenApiRule
{
    public const string ID = 'openapi-version';

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
        return 'The document declares OpenAPI 3.0, 3.1 or 3.2 in its `openapi` field.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        $version = $document->data['openapi'] ?? null;

        if (!\is_string($version) || 1 !== preg_match('/^3\.[0-2]\.\d+$/D', $version)) {
            yield Finding::of($this, '/openapi', 'The document declares no OpenAPI version 3.0, 3.1 or 3.2: "openapi": "3.1.0".');
        }
    }
}
