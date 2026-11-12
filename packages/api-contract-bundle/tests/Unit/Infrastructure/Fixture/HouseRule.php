<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure\Fixture;

use Override;
use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\OpenApi\Finding;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use Phil\ApiContract\OpenApi\RuleLevel;
use Phil\ApiContract\OpenApi\Severity;

/**
 * An application's own rule, which finds nothing.
 */
final readonly class HouseRule implements OpenApiRule
{
    #[Override]
    public function id(): string
    {
        return 'house-rule';
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
        return 'Nothing.';
    }

    /**
     * @return list<Finding>
     */
    #[Override]
    public function check(OpenApiDocument $document): iterable
    {
        return [];
    }
}
