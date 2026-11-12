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
 * No schema closes its values with `enum`; the values that may grow are listed in `x-extensible-enum`.
 *
 * A closed enumeration breaks every client that reads it strictly as soon as a value is added, which an unversioned
 * contract allows. `x-extensible-enum` lists the values known today, and the client reads them with a default, as
 * `TolerantEnum::withDefault()` does. The variables of a server URL are not schemas and are not reported.
 */
final readonly class ExtensibleEnum implements OpenApiRule
{
    public const string ID = 'extensible-enum';

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
        return 'No schema closes its values with `enum`; the values that may grow are listed in `x-extensible-enum`.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->nodes() as $pointer => $node) {
            if (\is_array($node['enum'] ?? null) && 1 !== preg_match('~/servers/\d+/variables/[^/]+\z~', $pointer)) {
                yield Finding::of($this, $pointer.'/enum', 'The schema closes its values with enum: list them in x-extensible-enum, which a value added later does not break.');
            }
        }
    }
}
