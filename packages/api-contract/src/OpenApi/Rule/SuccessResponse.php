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
 * Every operation declares a `2XX` or `3XX` response.
 *
 * A client generated from the document needs to know what a success looks like; an operation that documents only its
 * errors leaves it guessing.
 */
final readonly class SuccessResponse implements OpenApiRule
{
    public const string ID = 'success-response';

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
        return Severity::Error;
    }

    #[Override]
    public function description(): string
    {
        return 'Every operation declares a `2XX` or `3XX` response.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            $responses = $operation->operation['responses'] ?? null;
            $codes = \is_array($responses) ? array_keys($responses) : [];

            if ([] === preg_grep('/^[23](?:\d\d|XX)$/iD', $codes)) {
                yield Finding::of($this, $operation->pointer.'/responses', \sprintf('%s %s declares no success response.', strtoupper($operation->method), $operation->path));
            }
        }
    }
}
