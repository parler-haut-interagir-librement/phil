<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\OpenApi;

use Override;
use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\OpenApi\Finding;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;

/**
 * A rule that finds the same number of places in any document, each yielded under the same key, as a generator that
 * `yield from`s another may.
 */
final readonly class FixedRule implements OpenApiRule
{
    public function __construct(
        private string $id,
        private RuleLevel $level,
        private Severity $severity,
        private int $findings,
    ) {
    }

    #[Override]
    public function id(): string
    {
        return $this->id;
    }

    #[Override]
    public function level(): RuleLevel
    {
        return $this->level;
    }

    #[Override]
    public function severity(): Severity
    {
        return $this->severity;
    }

    #[Override]
    public function description(): string
    {
        return 'A fixed number of findings.';
    }

    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        for ($index = 0; $index < $this->findings; ++$index) {
            yield 'same' => Finding::of($this, '/'.$this->id.'/'.$index, \sprintf('Broken %d.', $index));
        }
    }
}
