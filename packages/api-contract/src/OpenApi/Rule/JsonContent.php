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
 * Every request body and response body of an operation is JSON: `application/json` or a `+json` media type.
 *
 * The API speaks JSON. A file the API serves as such, an image or a PDF, is a deliberate exception that this warning
 * lets through when the failure threshold is an error.
 */
final readonly class JsonContent implements OpenApiRule
{
    public const string ID = 'json-content';

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
        return 'Every request body and response body of an operation is JSON: `application/json` or a `+json` media type.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            $body = $document->resolve($operation->operation['requestBody'] ?? null);

            yield from $this->contentOf($body, $operation->pointer.'/requestBody');

            foreach ($operation->responses($document) as $code => $response) {
                yield from $this->contentOf($response, $operation->pointer.OpenApiDocument::pointer('responses', $code));
            }
        }
    }

    /**
     * @return iterable<Finding>
     */
    private function contentOf(mixed $holder, string $pointer): iterable
    {
        $content = \is_array($holder) ? ($holder['content'] ?? null) : null;

        foreach (\is_array($content) ? $content : [] as $mediaType => $media) {
            if (!self::isJson((string) $mediaType)) {
                yield Finding::of($this, $pointer.OpenApiDocument::pointer('content', $mediaType), \sprintf('The media type "%s" is not JSON.', $mediaType));
            }
        }
    }

    /**
     * `application/json`, or `application/` then a subtype ending in `+json`, parameters allowed, any case.
     */
    public static function isJson(string $mediaType): bool
    {
        $type = strtolower(trim(explode(';', $mediaType)[0]));

        return 'application/json' === $type || 1 === preg_match('~^application/[a-z0-9!#$&^_.-]+\+json\z~', $type);
    }
}
