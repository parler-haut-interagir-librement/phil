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
 * The document has a `paths` object whose every key is a path starting with `/`, holding a path item.
 *
 * A REST API is its paths: a document without them describes no endpoint, and a key that does not start with `/` is
 * no path template.
 */
final readonly class Paths implements OpenApiRule
{
    public const string ID = 'paths';

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
        return 'The document has a `paths` object whose every key is a path starting with `/`, holding a path item.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        $paths = $document->data['paths'] ?? null;

        if (!\is_array($paths) || ([] !== $paths && array_is_list($paths))) {
            yield Finding::of($this, '/paths', 'The document has no paths object.');

            return;
        }

        foreach ($paths as $path => $item) {
            $pointer = OpenApiDocument::pointer('paths', $path);

            if (!str_starts_with((string) $path, '/')) {
                yield Finding::of($this, $pointer, \sprintf('The path "%s" does not start with "/".', $path));
            } elseif (!\is_array($item)) {
                yield Finding::of($this, $pointer, \sprintf('The path "%s" holds no path item object.', $path));
            }
        }
    }
}
