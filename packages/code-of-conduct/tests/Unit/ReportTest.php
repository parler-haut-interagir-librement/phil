<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Tests\Unit;

use DateTimeImmutable;
use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;
use Phil\CodeOfConduct\NoteView;
use Phil\CodeOfConduct\Outcome;
use Phil\CodeOfConduct\Report;
use Phil\CodeOfConduct\ReportNote;
use Phil\CodeOfConduct\ReportStatus;
use Phil\CodeOfConduct\ReportView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Report::class)]
#[CoversClass(ReportStatus::class)]
#[CoversClass(ReportNote::class)]
#[CoversClass(ReportView::class)]
#[CoversClass(NoteView::class)]
final class ReportTest extends TestCase
{
    public function testItKeepsEachPersonNamedOnce(): void
    {
        $report = new Report('r', 'club', null, ['a', 'a', 'b'], 'sealed', null, new DateTimeImmutable('2026-12-01'), recused: ['x', 'x']);

        self::assertSame(['a', 'b'], $report->named);
        self::assertSame(['x'], $report->recused);
        self::assertTrue($report->names('b'));
        self::assertFalse($report->names('c'));
        self::assertSame(ReportStatus::Received, $report->status);
        self::assertNull($report->updatedAt);
    }

    public function testItNamesTwentyMembersAtMost(): void
    {
        $twenty = array_map(strval(...), range(1, 20));

        self::assertCount(20, new Report('r', 'club', 'sam', $twenty, 's', 1, new DateTimeImmutable())->named);

        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('A report names at most 20 members.');

        new Report('r', 'club', 'sam', [...$twenty, '21'], 's', 1, new DateTimeImmutable());
    }

    public function testAHundredResponsiblePeopleStepAsideAtMost(): void
    {
        $hundred = array_map(strval(...), range(1, 100));

        self::assertCount(100, new Report('r', 'club', null, [], 's', 1, new DateTimeImmutable(), recused: $hundred)->recused);

        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('At most 100 responsible people step aside from a report.');

        new Report('r', 'club', null, [], 's', 1, new DateTimeImmutable(), recused: [...$hundred, '101']);
    }

    /**
     * @return iterable<string, array{string, string, ?string, list<string>, list<string>, string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'report' => ['', 'club', null, [], [], 'A report'];
        yield 'scope' => ['r', '', null, [], [], 'A scope'];
        yield 'reporter' => ['r', 'club', '', [], [], 'A reporter'];
        yield 'named' => ['r', 'club', null, [''], [], 'A member named'];
        yield 'recused' => ['r', 'club', null, [], [''], 'A responsible person'];
    }

    /**
     * @param list<string> $named
     * @param list<string> $recused
     */
    #[DataProvider('badIdentifiers')]
    public function testEveryIdentifierIsChecked(string $id, string $scope, ?string $reporter, array $named, array $recused, string $what): void
    {
        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains($what.' is named by');

        new Report($id, $scope, $reporter, $named, 's', null, new DateTimeImmutable(), recused: $recused);
    }

    /**
     * @return iterable<string, array{ReportStatus, ?Outcome}>
     */
    public static function badOutcomes(): iterable
    {
        yield 'resolved without an outcome' => [ReportStatus::Resolved, null];
        yield 'dismissed with an outcome' => [ReportStatus::Dismissed, Outcome::Warning];
        yield 'received with an outcome' => [ReportStatus::Received, Outcome::NoAction];
    }

    #[DataProvider('badOutcomes')]
    public function testAnOutcomeGoesWithAResolutionOnly(ReportStatus $status, ?Outcome $outcome): void
    {
        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('A report carries an outcome when, and only when, it is resolved.');

        new Report('r', 'club', null, [], 's', null, new DateTimeImmutable(), $status, $outcome);
    }

    public function testItsWithersKeepEverythingElse(): void
    {
        $filed = new DateTimeImmutable('2026-12-01');
        $at = new DateTimeImmutable('2026-12-02');
        $report = new Report('r', 'club', 'sam', ['a'], 'sealed', 3, $filed, recused: ['x', 'z']);

        $resolved = $report->withStatus(ReportStatus::Resolved, Outcome::Warning, $at);
        $recused = $resolved->withRecusal('y', $at);

        foreach ([$resolved, $recused] as $changed) {
            self::assertSame(['r', 'club', 'sam', ['a'], 'sealed', 3, $filed], [$changed->id, $changed->scope, $changed->reporter, $changed->named, $changed->sealedText, $changed->codeVersion, $changed->filedAt]);
            self::assertSame(ReportStatus::Resolved, $changed->status);
            self::assertSame(Outcome::Warning, $changed->outcome);
            self::assertSame($at, $changed->updatedAt);
        }

        self::assertSame(['x', 'z'], $resolved->recused);
        self::assertSame(['x', 'z', 'y'], $recused->recused);
    }

    /**
     * @return iterable<string, array{ReportStatus, list<ReportStatus>}>
     */
    public static function transitions(): iterable
    {
        yield 'received' => [ReportStatus::Received, [ReportStatus::InReview, ReportStatus::Dismissed]];
        yield 'in review' => [ReportStatus::InReview, [ReportStatus::Resolved, ReportStatus::Dismissed]];
        yield 'resolved' => [ReportStatus::Resolved, [ReportStatus::InReview]];
        yield 'dismissed' => [ReportStatus::Dismissed, [ReportStatus::InReview]];
    }

    /**
     * @param list<ReportStatus> $allowed
     */
    #[DataProvider('transitions')]
    public function testEachStatusMovesOnlyWhereItMay(ReportStatus $from, array $allowed): void
    {
        foreach (ReportStatus::cases() as $to) {
            self::assertSame(\in_array($to, $allowed, true), $from->canBecome($to), $from->value.' to '.$to->value);
        }
    }

    public function testOnlyResolvedAndDismissedAreClosed(): void
    {
        self::assertSame([false, false, true, true], array_map(static fn (ReportStatus $status): bool => $status->isClosed(), ReportStatus::cases()));
    }

    public function testANoteChecksItsIdentifiers(): void
    {
        $at = new DateTimeImmutable();

        self::assertSame('lead', new ReportNote('n', 'r', 'lead', 's', $at)->author);

        foreach ([['', 'r', 'lead', 'A note'], ['n', '', 'lead', 'A report'], ['n', 'r', '', 'An author']] as [$id, $report, $author, $what]) {
            try {
                new ReportNote($id, $report, $author, 's', $at);
                self::fail($what.' passed empty.');
            } catch (InvalidCodeOfConduct $invalidCodeOfConduct) {
                self::assertStringStartsWith($what.' is named', $invalidCodeOfConduct->getMessage());
            }
        }
    }

    public function testViewsAreNeverDumpedWithTheirTexts(): void
    {
        $at = new DateTimeImmutable();
        $note = new NoteView('n', 'lead', 'Secret note', $at);
        $view = new ReportView(new Report('r', 'club', null, [], 's', null, $at), 'Secret text', [$note]);

        self::assertStringNotContainsString('Secret', print_r($view, true));
        self::assertSame(['report' => 'r', 'text' => '[sensitive]', 'notes' => 1], $view->__debugInfo());
        self::assertSame(['id' => 'n', 'author' => 'lead', 'text' => '[sensitive]'], $note->__debugInfo());
    }
}
