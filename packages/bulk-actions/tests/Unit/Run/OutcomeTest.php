<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Run;

use Phil\BulkActions\Run\ItemOutcome;
use Phil\BulkActions\Run\RunStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ItemOutcome::class)]
#[CoversClass(RunStatus::class)]
final class OutcomeTest extends TestCase
{
    public function testEachOutcomeHasItsTranslationKey(): void
    {
        $keys = array_map(static fn (ItemOutcome $outcome): string => $outcome->translationKey(), ItemOutcome::cases());

        self::assertSame([
            'bulk_actions.outcome.pending',
            'bulk_actions.outcome.succeeded',
            'bulk_actions.outcome.denied',
            'bulk_actions.outcome.conflict',
            'bulk_actions.outcome.refused',
            'bulk_actions.outcome.failed',
            'bulk_actions.outcome.missing',
        ], $keys);
    }

    public function testAFailureIsRetriedAndAConflictWhenAsked(): void
    {
        $retried = static fn (bool $conflicts): array => array_values(array_map(
            static fn (ItemOutcome $outcome): string => $outcome->value,
            array_filter(ItemOutcome::cases(), static fn (ItemOutcome $outcome): bool => $outcome->isRetryable($conflicts)),
        ));

        self::assertSame(['failed'], $retried(false));
        self::assertSame(['conflict', 'failed'], $retried(true));
    }

    public function testARunIsOverOnceFinishedOrStopped(): void
    {
        self::assertFalse(RunStatus::Pending->isOver());
        self::assertFalse(RunStatus::Running->isOver());
        self::assertTrue(RunStatus::Finished->isOver());
        self::assertTrue(RunStatus::Stopped->isOver());
    }
}
