<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Unit\Evaluation;

use Phil\Rules\Evaluation\NodeKind;
use Phil\Rules\Evaluation\Outcome;
use Phil\Rules\Evaluation\Step;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Step::class)]
final class StepTest extends TestCase
{
    public function testWritesItselfAsAnArray(): void
    {
        $leaf = new Step('/condition/all/0', NodeKind::Leaf, Outcome::Failed, 'fact.equals', 'fact.different', ['fact' => 'country']);
        $skipped = new Step('/condition/all/1', NodeKind::Any, Outcome::Skipped);
        $all = new Step('/condition', NodeKind::All, Outcome::Failed, children: [$leaf, $skipped]);

        self::assertSame([
            'path' => '/condition',
            'kind' => 'all',
            'outcome' => 'failed',
            'children' => [
                ['path' => '/condition/all/0', 'kind' => 'leaf', 'outcome' => 'failed', 'type' => 'fact.equals', 'reason' => 'fact.different', 'details' => ['fact' => 'country']],
                ['path' => '/condition/all/1', 'kind' => 'any', 'outcome' => 'skipped'],
            ],
        ], $all->toArray());
    }

    public function testALeafDecidesItself(): void
    {
        $leaf = new Step('/condition', NodeKind::Leaf, Outcome::Passed, 't');

        self::assertSame([$leaf], $leaf->decisive());
    }
}
