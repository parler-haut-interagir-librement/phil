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
 * Every operation marked `deprecated` documents a `Sunset` header on one of its responses.
 *
 * A deprecated operation announces when it goes away, in the `Sunset` header of RFC 8594, so that its clients can
 * plan; the document says so where the client reads it.
 */
final readonly class DeprecationSunset implements OpenApiRule
{
    public const string ID = 'deprecation-sunset';

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
        return 'Every operation marked `deprecated` documents a `Sunset` header on one of its responses.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            if (true !== ($operation->operation['deprecated'] ?? null)) {
                continue;
            }

            foreach ($operation->responses($document) as $response) {
                $headers = $response['headers'] ?? null;

                if (\is_array($headers) && \in_array('sunset', array_map(static fn (int|string $name): string => strtolower((string) $name), array_keys($headers)), true)) {
                    continue 2;
                }
            }

            yield Finding::of($this, $operation->pointer.'/deprecated', \sprintf('%s %s is deprecated but documents no Sunset header.', strtoupper($operation->method), $operation->path));
        }
    }
}
