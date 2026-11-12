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
 * Every local reference (`$ref` starting with `#`) points at a value of the document.
 *
 * A reference that points at nothing breaks every tool that reads the document. External references are not
 * followed and not reported.
 */
final readonly class LocalReferences implements OpenApiRule
{
    public const string ID = 'local-references';

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
        return 'Every local reference (`$ref` starting with `#`) points at a value of the document.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->nodes() as $pointer => $node) {
            $reference = $node['$ref'] ?? null;

            if (\is_string($reference) && str_starts_with($reference, '#') && null === $document->at($reference)) {
                yield Finding::of($this, $pointer.'/$ref', \sprintf('The reference "%s" points at nothing in the document.', $reference));
            }
        }
    }
}
