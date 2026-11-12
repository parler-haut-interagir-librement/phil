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
 * The document has an `info` object with a non-empty `title` and `version`.
 *
 * The title names the API and the version names the document, not the API: an unversioned API still dates its
 * description.
 */
final readonly class Info implements OpenApiRule
{
    public const string ID = 'info';

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
        return 'The document has an `info` object with a non-empty `title` and `version`.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        $info = $document->data['info'] ?? null;

        if (!\is_array($info)) {
            yield Finding::of($this, '/info', 'The document has no info object.');

            return;
        }

        foreach (['title', 'version'] as $field) {
            if (!\is_string($info[$field] ?? null) || '' === trim($info[$field])) {
                yield Finding::of($this, '/info/'.$field, \sprintf('The info object has no %s.', $field));
            }
        }
    }
}
