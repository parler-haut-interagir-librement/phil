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
 * No path and no server URL carries a version segment such as `v1` or `v2.1`.
 *
 * The contract has no version number: every change is additive, and a change that cannot be becomes a new resource
 * beside the old one, which announces its retirement. A version segment says otherwise.
 */
final readonly class NoVersionInPath implements OpenApiRule
{
    public const string ID = 'no-version-in-path';

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
        return 'No path and no server URL carries a version segment such as `v1` or `v2.1`.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        $servers = $document->data['servers'] ?? null;

        foreach (\is_array($servers) ? $servers : [] as $index => $server) {
            $url = \is_array($server) ? ($server['url'] ?? null) : null;

            if (\is_string($url) && $this->versioned($url)) {
                yield Finding::of($this, OpenApiDocument::pointer('servers', $index, 'url'), \sprintf('The server URL "%s" carries a version segment.', $url));
            }
        }

        $paths = $document->data['paths'] ?? null;

        foreach (\is_array($paths) ? $paths : [] as $path => $item) {
            if ($this->versioned((string) $path)) {
                yield Finding::of($this, OpenApiDocument::pointer('paths', $path), \sprintf('The path "%s" carries a version segment.', $path));
            }
        }
    }

    /**
     * Whether one of the `/`-separated segments of a path or a URL template is a version: `v`, then digits, then
     * optionally `.` and digits, any case.
     */
    private function versioned(string $template): bool
    {
        return array_any(explode('/', $template), static fn (string $segment): bool => 1 === preg_match('/^v\d+(?:\.\d+)*$/iD', $segment));
    }
}
