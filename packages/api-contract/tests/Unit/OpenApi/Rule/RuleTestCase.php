<?php

declare(strict_types=1);

namespace Phil\ApiContract\Tests\Unit\OpenApi\Rule;

use Phil\ApiContract\Contract\OpenApiRule;
use Phil\ApiContract\OpenApi\OpenApiDocument;
use PHPUnit\Framework\TestCase;

/**
 * What a rule finds in a document, as pairs of location and message.
 */
abstract class RuleTestCase extends TestCase
{
    /**
     * @param array<array-key, mixed> $document
     *
     * @return list<array{string, string}>
     */
    final protected function findings(OpenApiRule $rule, array $document): array
    {
        $findings = [];

        foreach ($rule->check(OpenApiDocument::fromArray($document)) as $finding) {
            self::assertSame([$rule->id(), $rule->severity()], [$finding->ruleId, $finding->severity]);
            $findings[] = [$finding->location, $finding->message];
        }

        return $findings;
    }
}
