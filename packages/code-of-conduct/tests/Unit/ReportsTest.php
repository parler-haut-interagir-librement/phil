<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\CodeOfConduct\ChangeKind;
use Phil\CodeOfConduct\CodeVersion;
use Phil\CodeOfConduct\Contract\TextSealer;
use Phil\CodeOfConduct\Event\ReportFiled;
use Phil\CodeOfConduct\Event\ReportNoteAdded;
use Phil\CodeOfConduct\Event\ReportRecused;
use Phil\CodeOfConduct\Event\ReportStatusChanged;
use Phil\CodeOfConduct\Event\ReportViewed;
use Phil\CodeOfConduct\Exception\ActionRefused;
use Phil\CodeOfConduct\Exception\InvalidCodeOfConduct;
use Phil\CodeOfConduct\Exception\ReportUnavailable;
use Phil\CodeOfConduct\Exception\UnreadableText;
use Phil\CodeOfConduct\KeyManagement\KeyManagementTextSealer;
use Phil\CodeOfConduct\Memory\InMemoryCodeVersionStore;
use Phil\CodeOfConduct\Memory\InMemoryReportStore;
use Phil\CodeOfConduct\NoteView;
use Phil\CodeOfConduct\Outcome;
use Phil\CodeOfConduct\Report;
use Phil\CodeOfConduct\ReportNote;
use Phil\CodeOfConduct\ReportPage;
use Phil\CodeOfConduct\ReportReceipt;
use Phil\CodeOfConduct\Reports;
use Phil\CodeOfConduct\ReportStatus;
use Phil\CodeOfConduct\ReportView;
use Phil\CodeOfConduct\Tests\Support\FixedResponsible;
use Phil\CodeOfConduct\Tests\Support\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

#[CoversClass(Reports::class)]
#[CoversClass(ReportPage::class)]
#[CoversClass(ReportReceipt::class)]
#[CoversClass(ReportUnavailable::class)]
#[CoversClass(ReportFiled::class)]
#[CoversClass(ReportViewed::class)]
#[CoversClass(ReportNoteAdded::class)]
#[CoversClass(ReportRecused::class)]
#[CoversClass(ReportStatusChanged::class)]
final class ReportsTest extends TestCase
{
    private InMemoryReportStore $store;

    private InMemoryCodeVersionStore $versions;

    private FixedResponsible $responsible;

    private RecordingDispatcher $events;

    private MockClock $clock;

    private Reports $reports;

    protected function setUp(): void
    {
        $this->store = new InMemoryReportStore();
        $this->versions = new InMemoryCodeVersionStore();
        $this->responsible = new FixedResponsible(['club' => ['lead', 'second'], 'fest' => ['fest-lead']]);
        $this->events = new RecordingDispatcher();
        $this->clock = new MockClock('2026-12-01 10:00:00');
        $this->reports = new Reports($this->store, $this->versions, $this->responsible, new KeyManagementTextSealer(new InMemoryKms('kms'), 'reports'), $this->clock, $this->events);
    }

    public function testAMemberFilesAReportUnderTheCodeInForce(): void
    {
        $this->versions->add(new CodeVersion('club', 1, 'Code', 'Be kind.', ChangeKind::Major, new DateTimeImmutable()));
        $this->versions->add(new CodeVersion('club', 2, 'Code', 'Be kind!', ChangeKind::Minor, new DateTimeImmutable()));

        $report = $this->reports->file('club', 'sam', ['alex', 'alex'], "  Alex shouted at me.\n ");

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $report->id);
        self::assertSame(['club', 'sam', ['alex'], 2, ReportStatus::Received], [$report->scope, $report->reporter, $report->named, $report->codeVersion, $report->status]);
        self::assertEquals(new DateTimeImmutable('2026-12-01 10:00:00'), $report->filedAt);
        self::assertSame($report, $this->store->get($report->id));
        self::assertStringNotContainsString('shouted', $this->decoded($report->sealedText));
        self::assertSame('Alex shouted at me.', $this->reports->read($report->id, 'lead')->text);
        self::assertEquals(new ReportFiled($report->id, 'club'), $this->events->events[0]);
    }

    public function testAnAnonymousReportWithoutACodeIsFiled(): void
    {
        $report = $this->reports->file('club', null, [], 'Something happened.');

        self::assertNull($report->reporter);
        self::assertNull($report->codeVersion);
        self::assertNotSame($report->id, $this->reports->file('club', null, [], 'Again.')->id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badTexts(): iterable
    {
        yield 'empty' => ['  '];
        yield 'too long' => [str_repeat('é', 20_001)];
    }

    #[DataProvider('badTexts')]
    public function testTheTextOfAReportHoldsOneTo20000Characters(string $text): void
    {
        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('The text of a report holds 1 to 20000 characters.');

        $this->reports->file('club', 'sam', [], $text);
    }

    public function testAReportOf20000CharactersIsFiled(): void
    {
        $report = $this->reports->file('club', 'sam', [], str_repeat('é', 20_000));

        self::assertSame(20_000, mb_strlen($this->reports->read($report->id, 'lead')->text));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function unreachable(): iterable
    {
        yield 'every responsible person named' => ['club', ['lead', 'second']];
        yield 'a scope without responsible people' => ['nobody', []];
    }

    /**
     * @param list<string> $named
     */
    #[DataProvider('unreachable')]
    public function testAReportNobodyCouldReceiveIsRefused(string $scope, array $named): void
    {
        try {
            $this->reports->file($scope, 'sam', $named, 'Something happened.');
            self::fail('The report was filed.');
        } catch (ActionRefused $actionRefused) {
            self::assertSame('No responsible person outside the people named can receive this report; use the contact outside the site.', $actionRefused->getMessage());
        }

        self::assertSame([], $this->store->inScope($scope, null, null, 10));
        self::assertSame([], $this->events->events);
    }

    public function testAReportNamingOneResponsiblePersonGoesToTheOthers(): void
    {
        $report = $this->reports->file('club', 'sam', ['lead'], 'The lead did something.');

        self::assertSame('The lead did something.', $this->reports->read($report->id, 'second')->text);
        $this->assertUnavailable(fn (): ReportView => $this->reports->read($report->id, 'lead'));
        self::assertSame([], $this->reports->queue('club', 'lead')->reports);
    }

    public function testTheReporterFollowsWhereTheirReportStands(): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');
        $this->clock->sleep(60);
        $this->reports->review($report->id, 'lead');

        $receipt = $this->reports->receipt($report->id, 'sam');

        self::assertSame([$report->id, ReportStatus::InReview], [$receipt->id, $receipt->status]);
        self::assertEquals(new DateTimeImmutable('2026-12-01 10:00:00'), $receipt->filedAt);
        self::assertEquals(new DateTimeImmutable('2026-12-01 10:01:00'), $receipt->updatedAt);
    }

    public function testNobodyElseReadsTheReceipt(): void
    {
        $named = $this->reports->file('club', 'sam', [], 'Something happened.');
        $anonymous = $this->reports->file('club', null, [], 'Something happened.');

        $this->assertUnavailable(fn (): ReportReceipt => $this->reports->receipt($named->id, 'alex'));
        $this->assertUnavailable(fn (): ReportReceipt => $this->reports->receipt($named->id, 'lead'));
        $this->assertUnavailable(fn (): ReportReceipt => $this->reports->receipt($anonymous->id, 'sam'));
        $this->assertUnavailable(fn (): ReportReceipt => $this->reports->receipt('unknown', 'sam'));
    }

    public function testAResponsiblePersonReadsTheReportAndItsNotes(): void
    {
        $report = $this->reports->file('club', 'sam', ['alex'], 'Something happened.');
        $note = $this->reports->addNote($report->id, 'lead', ' Spoke with Sam. ');
        $this->clock->sleep(60);
        $this->reports->addNote($report->id, 'second', 'Spoke with Alex.');
        $this->events->events = [];

        $view = $this->reports->read($report->id, 'second');

        self::assertSame($report->id, $view->report->id);
        self::assertSame('Something happened.', $view->text);
        self::assertSame(['Spoke with Sam.', 'Spoke with Alex.'], array_map(static fn (NoteView $view): string => $view->text, $view->notes));
        self::assertSame([$note->id, 'lead'], [$view->notes[0]->id, $view->notes[0]->author]);
        self::assertEquals(new DateTimeImmutable('2026-12-01 10:00:00'), $view->notes[0]->writtenAt);
        self::assertEquals([new ReportViewed($report->id, 'second')], $this->events->events);
    }

    public function testANoteIsSealedAndAnnounced(): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');
        $this->events->events = [];
        $note = $this->reports->addNote($report->id, 'lead', 'Spoke with Sam.');

        self::assertSame([$report->id, 'lead'], [$note->report, $note->author]);
        self::assertStringNotContainsString('Spoke', $this->decoded($note->sealedText));
        self::assertEquals([new ReportNoteAdded($report->id, $note->id, 'lead')], $this->events->events);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNotes(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('é', 10_001)];
    }

    #[DataProvider('badNotes')]
    public function testANoteHoldsOneTo10000Characters(string $text): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');

        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('A note holds 1 to 10000 characters.');

        $this->reports->addNote($report->id, 'lead', $text);
    }

    public function testAReportHolds500NotesAtMost(): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');
        $this->reports->addNote($report->id, 'lead', str_repeat('é', 10_000));

        for ($i = 1; $i < 499; ++$i) {
            $this->store->addNote(new ReportNote('n'.$i, $report->id, 'lead', 'x', new DateTimeImmutable()));
        }

        $this->reports->addNote($report->id, 'lead', 'The 500th note.');

        $this->expectException(ActionRefused::class);
        $this->expectExceptionMessageIsOrContains('A report holds 500 notes at most.');

        $this->reports->addNote($report->id, 'lead', 'One too many.');
    }

    public function testAReportIsReviewedThenResolvedWithAnOutcome(): void
    {
        $report = $this->reports->file('club', 'sam', ['alex'], 'Something happened.');
        $this->events->events = [];
        $this->clock->sleep(60);

        $inReview = $this->reports->review($report->id, 'lead');
        $resolved = $this->reports->resolve($report->id, 'second', Outcome::Warning);

        self::assertSame(ReportStatus::InReview, $inReview->status);
        self::assertSame([ReportStatus::Resolved, Outcome::Warning], [$resolved->status, $resolved->outcome]);
        self::assertEquals(new DateTimeImmutable('2026-12-01 10:01:00'), $resolved->updatedAt);
        self::assertSame($resolved, $this->store->get($report->id));
        self::assertEquals([
            new ReportStatusChanged($report->id, 'lead', ReportStatus::InReview, null),
            new ReportStatusChanged($report->id, 'second', ReportStatus::Resolved, Outcome::Warning),
        ], $this->events->events);

        $reopened = $this->reports->review($report->id, 'lead');

        self::assertSame(ReportStatus::InReview, $reopened->status);
        self::assertNull($reopened->outcome);
        self::assertSame(ReportStatus::Dismissed, $this->reports->dismiss($report->id, 'lead')->status);
    }

    public function testAReportReceivedIsDismissed(): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');

        self::assertSame(ReportStatus::Dismissed, $this->reports->dismiss($report->id, 'lead')->status);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedMoves(): iterable
    {
        yield 'resolving a report received' => ['resolve', 'A report received cannot become resolved.'];
        yield 'reviewing a report in review' => ['review twice', 'A report in_review cannot become in_review.'];
        yield 'dismissing a report dismissed' => ['dismiss twice', 'A report dismissed cannot become dismissed.'];
    }

    #[DataProvider('refusedMoves')]
    public function testAReportMovesOnlyWhereItsStatusAllows(string $move, string $message): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');

        if ('review twice' === $move) {
            $this->reports->review($report->id, 'lead');
        }

        if ('dismiss twice' === $move) {
            $this->reports->dismiss($report->id, 'lead');
        }

        $this->events->events = [];

        try {
            match ($move) {
                'resolve' => $this->reports->resolve($report->id, 'lead', Outcome::NoAction),
                'review twice' => $this->reports->review($report->id, 'lead'),
                default => $this->reports->dismiss($report->id, 'lead'),
            };
            self::fail('The report moved.');
        } catch (ActionRefused $actionRefused) {
            self::assertSame($message, $actionRefused->getMessage());
        }

        self::assertSame([], $this->events->events);
    }

    public function testAResponsiblePersonStepsAside(): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');
        $this->events->events = [];
        $this->clock->sleep(60);

        $recused = $this->reports->recuse($report->id, 'lead');

        self::assertSame(['lead'], $recused->recused);
        self::assertEquals(new DateTimeImmutable('2026-12-01 10:01:00'), $recused->updatedAt);
        self::assertEquals([new ReportRecused($report->id, 'lead')], $this->events->events);
        $this->assertUnavailable(fn (): ReportView => $this->reports->read($report->id, 'lead'));
        $this->assertUnavailable(fn (): Report => $this->reports->recuse($report->id, 'lead'));
        self::assertSame('Something happened.', $this->reports->read($report->id, 'second')->text);
        self::assertSame([], $this->reports->queue('club', 'lead')->reports);
    }

    public function testNoOneOutsideTheResponsiblePeopleHandlesAReport(): void
    {
        $report = $this->reports->file('club', 'sam', ['alex'], 'Something happened.');
        $this->events->events = [];

        foreach (['sam', 'alex', 'fest-lead', 'stranger'] as $actor) {
            $this->assertUnavailable(fn (): ReportView => $this->reports->read($report->id, $actor));
            $this->assertUnavailable(fn (): ReportNote => $this->reports->addNote($report->id, $actor, 'Note'));
            $this->assertUnavailable(fn (): Report => $this->reports->review($report->id, $actor));
            $this->assertUnavailable(fn (): Report => $this->reports->resolve($report->id, $actor, Outcome::NoAction));
            $this->assertUnavailable(fn (): Report => $this->reports->dismiss($report->id, $actor));
            $this->assertUnavailable(fn (): Report => $this->reports->recuse($report->id, $actor));
        }

        $this->assertUnavailable(fn (): ReportView => $this->reports->read('unknown', 'lead'));
        self::assertSame([], $this->events->events);
        self::assertSame(ReportStatus::Received, $this->store->get($report->id)?->status);
    }

    public function testAResponsiblePersonRemovedFromTheScopeLosesTheReport(): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');
        $this->responsible->people['club'] = ['second'];

        $this->assertUnavailable(fn (): ReportView => $this->reports->read($report->id, 'lead'));
    }

    public function testTheQueueListsWhatTheResponsiblePersonMayHandle(): void
    {
        $first = $this->reports->file('club', 'sam', [], 'One.');
        $naming = $this->reports->file('club', 'sam', ['lead'], 'Two.');
        $third = $this->reports->file('club', 'sam', [], 'Three.');
        $this->reports->file('fest', 'sam', [], 'Elsewhere.');
        $this->reports->review($third->id, 'second');

        self::assertEquals(new ReportPage([$first, $this->stored($third->id)], null), $this->reports->queue('club', 'lead'));
        self::assertEquals(new ReportPage([$first, $this->stored($naming->id)], $naming->id), $this->reports->queue('club', 'second', null, null, 2));
        self::assertEquals(new ReportPage([$first], $naming->id), $this->reports->queue('club', 'lead', null, null, 2));
        self::assertEquals(new ReportPage([$this->stored($third->id)], null), $this->reports->queue('club', 'lead', null, $naming->id, 2));
        self::assertEquals(new ReportPage([$first], null), $this->reports->queue('club', 'lead', ReportStatus::Received));
        self::assertEquals(new ReportPage([$first, $this->stored($naming->id), $this->stored($third->id)], $third->id), $this->reports->queue('club', 'second', null, null, 3));
    }

    public function testTheQueueIsForTheResponsiblePeopleOfTheScope(): void
    {
        $this->assertUnavailable(fn (): ReportPage => $this->reports->queue('club', 'fest-lead'));
        $this->assertUnavailable(fn (): ReportPage => $this->reports->queue('club', 'sam'));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function badPages(): iterable
    {
        yield 'zero' => [0];
        yield '101' => [101];
    }

    #[DataProvider('badPages')]
    public function testAPageReadsOneTo100Reports(int $limit): void
    {
        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('A page of reports reads 1 to 100 reports.');

        $this->reports->queue('club', 'lead', null, null, $limit);
    }

    public function testAPageOf100ReportsIsRead(): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');

        self::assertEquals(new ReportPage([$report], null), $this->reports->queue('club', 'lead', null, null, 100));
        self::assertEquals(new ReportPage([$report], $report->id), $this->reports->queue('club', 'lead', null, null, 1));
    }

    public function testTheQueueReadsFiftyReportsByDefault(): void
    {
        $filed = [];

        for ($i = 0; $i < 51; ++$i) {
            $filed[] = $this->reports->file('club', 'sam', [], 'Something happened.');
        }

        $page = $this->reports->queue('club', 'lead');

        self::assertCount(50, $page->reports);
        self::assertSame($filed[49]->id, $page->next);
    }

    public function testTheTextsAreSealedUnderTheContextOfTheirReportAndNote(): void
    {
        $inner = new KeyManagementTextSealer(new InMemoryKms('kms'), 'reports');
        $sealer = new class($inner) implements TextSealer {
            /** @var list<string> */
            public array $contexts = [];

            public function __construct(private readonly TextSealer $inner)
            {
            }

            #[Override]
            public function seal(#[SensitiveParameter] string $plaintext, string $context): string
            {
                $this->contexts[] = $context;

                return $this->inner->seal($plaintext, $context);
            }

            #[Override]
            public function open(string $sealed, string $context): string
            {
                $this->contexts[] = $context;

                return $this->inner->open($sealed, $context);
            }
        };
        $reports = new Reports($this->store, $this->versions, $this->responsible, $sealer, $this->clock);

        $report = $reports->file('club', 'sam', [], 'Something happened.');
        $note = $reports->addNote($report->id, 'lead', 'Note.');
        $reports->read($report->id, 'lead');

        self::assertSame([
            "phil/code-of-conduct\x1Fv1\x1Freport\x1F".$report->id,
            "phil/code-of-conduct\x1Fv1\x1Fnote\x1F".$report->id."\x1F".$note->id,
            "phil/code-of-conduct\x1Fv1\x1Fnote\x1F".$report->id."\x1F".$note->id,
            "phil/code-of-conduct\x1Fv1\x1Freport\x1F".$report->id,
        ], $sealer->contexts);
    }

    public function testAScopeHas100ResponsiblePeopleAtMost(): void
    {
        $this->responsible->people['club'] = array_map(strval(...), range(1, 100));
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');

        self::assertSame('Something happened.', $this->reports->read($report->id, '100')->text);

        $this->responsible->people['club'][] = '101';

        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains('A scope has 100 responsible people at most.');

        $this->reports->read($report->id, '1');
    }

    public function testATextMovedToAnotherReportDoesNotOpen(): void
    {
        $one = $this->reports->file('club', 'sam', [], 'One.');
        $two = $this->reports->file('club', 'sam', [], 'Two.');
        $this->store->save(new Report($two->id, 'club', 'sam', [], $one->sealedText, null, $two->filedAt));

        $this->expectException(UnreadableText::class);

        $this->reports->read($two->id, 'lead');
    }

    public function testANoteMovedToAnotherReportDoesNotOpen(): void
    {
        $one = $this->reports->file('club', 'sam', [], 'One.');
        $two = $this->reports->file('club', 'sam', [], 'Two.');
        $note = $this->reports->addNote($one->id, 'lead', 'Note.');
        $this->store->addNote(new ReportNote($note->id, $two->id, 'lead', $note->sealedText, $note->writtenAt));

        $this->expectException(UnreadableText::class);

        $this->reports->read($two->id, 'lead');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badIdentifiers(): iterable
    {
        yield 'file, scope' => ['file', 'A scope'];
        yield 'file, scope before the text' => ['fileScopeAndText', 'A scope'];
        yield 'file, reporter' => ['fileReporter', 'A reporter'];
        yield 'receipt, report' => ['receipt', 'A report'];
        yield 'receipt, reporter' => ['receiptReporter', 'A reporter'];
        yield 'queue, scope' => ['queue', 'A scope'];
        yield 'queue, actor' => ['queueActor', 'A responsible person'];
        yield 'queue, after' => ['queueAfter', 'A report'];
        yield 'read, report' => ['read', 'A report'];
        yield 'read, actor' => ['readActor', 'A responsible person'];
    }

    #[DataProvider('badIdentifiers')]
    public function testEveryPathChecksItsIdentifiers(string $path, string $what): void
    {
        $report = $this->reports->file('club', 'sam', [], 'Something happened.');

        $this->expectException(InvalidCodeOfConduct::class);
        $this->expectExceptionMessageIsOrContains($what.' is named by');

        match ($path) {
            'file' => $this->reports->file('', 'sam', [], 'Text'),
            'fileScopeAndText' => $this->reports->file('', 'sam', [], ''),
            'fileReporter' => $this->reports->file('club', '', [], 'Text'),
            'receipt' => $this->reports->receipt('', 'sam'),
            'receiptReporter' => $this->reports->receipt($report->id, ''),
            'queue' => $this->reports->queue('', 'lead'),
            'queueActor' => $this->reports->queue('club', ''),
            'queueAfter' => $this->reports->queue('club', 'lead', null, ''),
            'read' => $this->reports->read('', 'lead'),
            default => $this->reports->read($report->id, ''),
        };
    }

    public function testItWorksWithoutADispatcher(): void
    {
        $reports = new Reports($this->store, $this->versions, $this->responsible, new KeyManagementTextSealer(new InMemoryKms('kms'), 'reports'), $this->clock);
        $report = $reports->file('club', 'sam', [], 'Something happened.');
        $reports->addNote($report->id, 'lead', 'Note.');
        $reports->review($report->id, 'lead');
        $reports->recuse($report->id, 'lead');

        self::assertCount(1, $reports->read($report->id, 'second')->notes);
    }

    private function decoded(string $sealed): string
    {
        $bytes = base64_decode($sealed, true);
        self::assertIsString($bytes);

        return $bytes;
    }

    private function stored(string $id): Report
    {
        $report = $this->store->get($id);
        self::assertInstanceOf(Report::class, $report);

        return $report;
    }

    private function assertUnavailable(callable $action): void
    {
        try {
            $action();
            self::fail('The action passed.');
        } catch (ReportUnavailable $reportUnavailable) {
            self::assertSame('This report is not available to you.', $reportUnavailable->getMessage());
        }
    }
}
