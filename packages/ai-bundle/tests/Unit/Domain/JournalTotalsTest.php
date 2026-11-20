<?php

declare(strict_types=1);

namespace Phil\AiBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\AiBundle\Domain\Journal\Decision;
use Phil\AiBundle\Domain\Journal\JournalTotals;
use Phil\AiBundle\Domain\Journal\KeySource;
use Phil\AiBundle\Domain\Model\JournalEntry;
use Phil\AiBundle\Domain\ValueObject\Cost;
use Phil\AiBundle\Domain\ValueObject\Subject;
use Phil\AiBundle\Domain\ValueObject\TaskKind;
use Phil\AiBundle\Domain\ValueObject\Tokens;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JournalTotals::class)]
final class JournalTotalsTest extends TestCase
{
    public function testTheTasksAddUp(): void
    {
        $at = new DateTimeImmutable('2026-11-06 10:00:00 UTC');
        $kind = TaskKind::fromString('draft.rewrite');
        $subject = Subject::fromString('project-1');
        $accepted = JournalEntry::succeeded('t-1', $kind, $subject, 'main', 'm', KeySource::Application, new Tokens(10, 5), new Cost(100, 'USD'), $at, 1);
        $accepted->decide(Decision::Accepted, $at);

        $rejected = JournalEntry::succeeded('t-2', $kind, $subject, 'main', 'm', KeySource::User, new Tokens(20, 7), new Cost(200, 'USD'), $at, 1);
        $rejected->decide(Decision::Rejected, $at);

        $pending = JournalEntry::succeeded('t-3', $kind, $subject, 'main', 'm', KeySource::Application, new Tokens(1, 1), new Cost(3, 'EUR'), $at, 1);
        $failed = JournalEntry::failed('t-4', $kind, $subject, 'main', 'm', KeySource::Application, 'USD', $at, 1);

        $totals = JournalTotals::of($accepted, $rejected, $pending, $failed);

        self::assertSame([4, 1], [$totals->tasks, $totals->failed]);
        self::assertSame([31, 13], [$totals->tokens->input, $totals->tokens->output]);
        self::assertSame(['USD' => 300, 'EUR' => 3], $totals->costs);
        self::assertSame(['pending' => 1, 'accepted' => 1, 'edited' => 0, 'rejected' => 1], $totals->decisions);

        $none = JournalTotals::of();
        self::assertSame([0, 0, 0, [], ['pending' => 0, 'accepted' => 0, 'edited' => 0, 'rejected' => 0]], [$none->tasks, $none->failed, $none->tokens->total(), $none->costs, $none->decisions]);
    }

    public function testTheTotalsAreReadFromTheSumsOfAStore(): void
    {
        $totals = JournalTotals::fromGroups([
            ['outcome' => 'succeeded', 'decision' => 'accepted', 'currency' => 'USD', 'tasks' => '2', 'input' => '30', 'output' => 12, 'cost' => '300'],
            ['outcome' => 'succeeded', 'decision' => 'pending', 'currency' => 'EUR', 'tasks' => 1, 'input' => 1, 'output' => '1', 'cost' => 3],
            ['outcome' => 'succeeded', 'decision' => 'accepted', 'currency' => 'EUR', 'tasks' => 3, 'input' => 0, 'output' => 0, 'cost' => 4],
            ['outcome' => 'failed', 'decision' => 'pending', 'currency' => 'USD', 'tasks' => '4', 'input' => null, 'output' => null, 'cost' => null],
            ['outcome' => 'failed', 'decision' => 'accepted', 'currency' => 'USD', 'tasks' => 2, 'input' => 0, 'output' => 0, 'cost' => 0],
        ]);

        self::assertSame([12, 6], [$totals->tasks, $totals->failed]);
        self::assertSame([31, 13], [$totals->tokens->input, $totals->tokens->output]);
        self::assertSame(['USD' => 300, 'EUR' => 7], $totals->costs);
        self::assertSame(['pending' => 1, 'accepted' => 5, 'edited' => 0, 'rejected' => 0], $totals->decisions);
        self::assertEquals(JournalTotals::of(), JournalTotals::fromGroups([]));
    }
}
