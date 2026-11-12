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
 * Every `{name}` of a path template is a required path parameter of each of its operations, and every path parameter appears in the template.
 *
 * A template variable without its parameter, or a path parameter missing from the template, makes a URL no client can
 * build. A path parameter is always required.
 */
final readonly class PathParameters implements OpenApiRule
{
    public const string ID = 'path-parameters';

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
        return 'Every `{name}` of a path template is a required path parameter of each of its operations, and every path parameter appears in the template.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        foreach ($document->operations() as $operation) {
            preg_match_all('/\{([^{}\/]+)\}/', $operation->path, $matches);
            $template = array_fill_keys($matches[1], true);
            $declared = [];

            foreach ($operation->parameters($document) as $pointer => $parameter) {
                if ('path' !== $parameter['in']) {
                    continue;
                }

                $name = $parameter['name'];
                $declared[] = $name;

                if (!isset($template[$name])) {
                    yield Finding::of($this, $pointer, \sprintf('The path parameter "%s" is not in the template %s.', $name, $operation->path));
                } elseif (true !== ($parameter['required'] ?? null)) {
                    yield Finding::of($this, $pointer.'/required', \sprintf('The path parameter "%s" is not marked required.', $name));
                }
            }

            foreach (array_diff(array_keys($template), $declared) as $name) {
                yield Finding::of($this, $operation->pointer, \sprintf('%s %s does not declare the path parameter "%s".', strtoupper($operation->method), $operation->path, $name));
            }
        }
    }
}
