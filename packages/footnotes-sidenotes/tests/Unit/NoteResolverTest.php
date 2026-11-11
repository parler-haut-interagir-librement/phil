<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit;

use Phil\FootnotesSidenotes\Internal\NoteCollector;
use Phil\FootnotesSidenotes\Note;
use Phil\FootnotesSidenotes\NoteOptions;
use Phil\FootnotesSidenotes\NoteReference;
use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Numbering;
use Phil\FootnotesSidenotes\Problem;
use Phil\FootnotesSidenotes\ProblemKind;
use Phil\FootnotesSidenotes\Resolution;
use Phil\FootnotesSidenotes\Restart;
use Phil\FootnotesSidenotes\Tests\Unit\DocumentFactory as F;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoteResolver::class)]
#[CoversClass(NoteCollector::class)]
#[CoversClass(Resolution::class)]
#[CoversClass(Note::class)]
#[CoversClass(NoteReference::class)]
#[CoversClass(Problem::class)]
#[UsesClass(NoteOptions::class)]
final class NoteResolverTest extends TestCase
{
    public function testAnEmptyDocumentHasNoNotes(): void
    {
        $resolution = new NoteResolver()->resolve(new Document());

        self::assertSame([], $resolution->notes);
        self::assertSame([], $resolution->problems);
        self::assertTrue($resolution->isClean());
    }

    public function testNotesAreNumberedInTheOrderOfTheirFirstReference(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([
            F::note('n-b', 'b', 'Second <b>note</b>'),
            F::paragraph('p1', 'One'.F::reference('a').' two'.F::reference('b')),
            F::note('n-a', 'a', 'First note'),
        ]));

        self::assertSame(['a', 'b'], array_map(static fn (Note $note): string => $note->id, $resolution->notes));
        self::assertSame([1, 2], array_map(static fn (Note $note): int => $note->number, $resolution->notes));
        self::assertSame(['1', '2'], array_map(static fn (Note $note): string => $note->label, $resolution->notes));

        $note = $resolution->note('b');
        self::assertNotNull($note);
        self::assertSame('Second <b>note</b>', $note->html);
        self::assertSame('n-b', $note->blockId);
        self::assertSame(0, $note->scope);
        self::assertSame('p1', $note->references[0]->blockId);
        self::assertSame(1, $note->references[0]->occurrence);
        self::assertNull($resolution->note('c'));
        self::assertTrue($resolution->isClean());
    }

    public function testReferencesAreFoundInEveryPlaceOfABlockAndInNestedBlocks(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([
            ['id' => 'list', 'type' => 'list', 'data' => ['style' => 'unordered', 'items' => [['content' => 'Item'.F::reference('a'), 'items' => [['content' => 'Sub'.F::reference('b')]]]]]],
            ['id' => 'col', 'type' => 'columns', 'data' => ['cols' => [['blocks' => [F::paragraph('in', 'Nested'.F::reference('c')), F::note('deep', 'c', 'Deep')]], ['blocks' => [F::paragraph('in2', 'x')]]]]],
            F::note('n-a', 'a', 'A'),
            F::note('n-b', 'b', 'B'),
        ]));

        self::assertSame(['a', 'b', 'c'], array_map(static fn (Note $note): string => $note->id, $resolution->notes));
        $nested = $resolution->note('c');
        self::assertNotNull($nested);
        self::assertSame('in', $nested->references[0]->blockId);
        self::assertSame(1, $nested->firstTopLevelIndex());
        self::assertSame('deep', $nested->blockId);
        self::assertSame(0, $resolution->note('a')?->firstTopLevelIndex());
        self::assertSame([], $resolution->problems);
    }

    public function testWhatLooksLikeAReferenceInCodeIsNotOne(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([
            ['id' => 'code', 'type' => 'code', 'data' => ['code' => F::reference('a')]],
            ['id' => 'graph', 'type' => 'mermaid', 'data' => ['code' => F::reference('a')]],
            ['id' => 'tex', 'type' => 'math', 'data' => ['latex' => F::reference('a')]],
        ]));

        self::assertSame([], $resolution->notes);
        self::assertSame([], $resolution->problems);
    }

    public function testNumberingRestartsAtEachChapter(): void
    {
        $document = F::document([
            F::paragraph('intro', F::reference('a')),
            F::heading('h1', 1),
            F::paragraph('p1', F::reference('b').F::reference('c')),
            F::heading('h2', 2),
            F::paragraph('p2', F::reference('d')),
            F::heading('h3', 1),
            F::paragraph('p3', F::reference('e')),
            F::note('na', 'a', 'A'), F::note('nb', 'b', 'B'), F::note('nc', 'c', 'C'), F::note('nd', 'd', 'D'), F::note('ne', 'e', 'E'),
        ]);

        self::assertSame(['1', '1', '2', '3', '1'], $this->labels($document, new NoteOptions(restart: Restart::Chapter)));
        self::assertSame(['1', '1', '2', '1', '1'], $this->labels($document, new NoteOptions(restart: Restart::Chapter, chapterLevel: 2)));
        self::assertSame([0, 1, 1, 1, 2], array_map(static fn (Note $note): int => $note->scope, new NoteResolver(new NoteOptions(restart: Restart::Chapter))->resolve($document)->notes));
    }

    public function testNumberingRestartsAtEachPageMarker(): void
    {
        $document = F::document([
            F::paragraph('p1', F::reference('a').F::reference('b')),
            ['id' => 'break', 'type' => 'pageBreak', 'data' => []],
            F::paragraph('p2', F::reference('c')),
            ['id' => 'column', 'type' => 'columnBreak', 'data' => []],
            F::paragraph('p3', F::reference('d')),
            F::note('na', 'a', 'A'), F::note('nb', 'b', 'B'), F::note('nc', 'c', 'C'), F::note('nd', 'd', 'D'),
        ]);

        self::assertSame(['1', '2', '1', '2'], $this->labels($document, new NoteOptions(restart: Restart::PageMarker)));
        self::assertSame(['1', '2', '3', '1'], $this->labels($document, new NoteOptions(restart: Restart::PageMarker, pageMarkers: ['columnBreak'])));
    }

    public function testNestedBlocksCountForRestarts(): void
    {
        $document = F::document([
            F::paragraph('p1', F::reference('a')),
            ['id' => 'wide', 'type' => 'wide', 'data' => ['blocks' => [F::heading('h', 1), F::paragraph('p2', F::reference('b'))]]],
            F::note('na', 'a', 'A'), F::note('nb', 'b', 'B'),
        ]);

        self::assertSame(['1', '1'], $this->labels($document, new NoteOptions(restart: Restart::Chapter)));
    }

    public function testADocumentNumberingIgnoresChaptersAndPages(): void
    {
        $document = F::document([
            F::paragraph('p1', F::reference('a')),
            F::heading('h', 1),
            ['id' => 'break', 'type' => 'pageBreak', 'data' => []],
            F::paragraph('p2', F::reference('b')),
            F::note('na', 'a', 'A'), F::note('nb', 'b', 'B'),
        ]);

        self::assertSame(['1', '2'], $this->labels($document, new NoteOptions()));
    }

    public function testTheLocaleChoosesTheNumbering(): void
    {
        $document = F::document([F::paragraph('p', F::reference('a').F::reference('b')), F::note('na', 'a', 'A'), F::note('nb', 'b', 'B')]);
        $resolver = new NoteResolver(new NoteOptions(numbering: Numbering::lowerRoman(), locales: ['fr' => Numbering::symbols()]));

        self::assertSame(['i', 'ii'], array_map(static fn (Note $note): string => $note->label, $resolver->resolve($document)->notes));
        self::assertSame(['*', '†'], array_map(static fn (Note $note): string => $note->label, $resolver->resolve($document, 'fr-CA')->notes));
    }

    public function testAReferenceWithoutNoteIsReported(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([F::paragraph('p', F::reference('ghost').F::reference('real')), F::note('n', 'real', 'Real')]));
        $problems = $resolution->problemsOf(ProblemKind::MissingNote);

        self::assertCount(1, $problems);
        self::assertSame('ghost', $problems[0]->noteId);
        self::assertSame('p', $problems[0]->blockId);
        self::assertFalse($resolution->isClean());
        self::assertSame('1', $resolution->note('real')?->label);
    }

    public function testANoteNeverReferencedIsReported(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([F::note('n', 'alone', 'Alone'), F::paragraph('p', 'x')]));
        $problems = $resolution->problemsOf(ProblemKind::UnreferencedNote);

        self::assertCount(1, $problems);
        self::assertSame('alone', $problems[0]->noteId);
        self::assertSame('n', $problems[0]->blockId);
        self::assertNull($resolution->note('alone'));
        self::assertFalse($resolution->isClean());
    }

    public function testADuplicateIdentifierKeepsTheFirstNote(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([
            F::paragraph('p', F::reference('a')),
            F::note('n1', 'a', 'First'),
            F::note('n2', 'a', 'Second'),
        ]));
        $problems = $resolution->problemsOf(ProblemKind::DuplicateId);

        self::assertCount(1, $problems);
        self::assertSame('a', $problems[0]->noteId);
        self::assertSame('n2', $problems[0]->blockId);
        self::assertSame('First', $resolution->note('a')?->html);
    }

    public function testANoteReferencedSeveralTimesIsOneNoteWithSeveralReferences(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([
            F::paragraph('p1', F::reference('a')),
            F::paragraph('p2', F::reference('b').F::reference('a')),
            F::paragraph('p3', F::reference('a')),
            F::note('n', 'a', 'A'), F::note('m', 'b', 'B'),
        ]));

        $note = $resolution->note('a');
        self::assertNotNull($note);
        self::assertSame(['p1', 'p2', 'p3'], array_map(static fn (NoteReference $reference): string => $reference->blockId, $note->references));
        self::assertSame([1, 2, 3], array_map(static fn (NoteReference $reference): int => $reference->occurrence, $note->references));
        self::assertSame([0, 1, 2], array_map(static fn (NoteReference $reference): int => $reference->topLevelIndex, $note->references));
        self::assertSame('2', $resolution->note('b')?->label);

        $problems = $resolution->problemsOf(ProblemKind::RepeatedReference);
        self::assertCount(1, $problems);
        self::assertSame('a', $problems[0]->noteId);
        self::assertSame('p2', $problems[0]->blockId);
        self::assertSame(3, $problems[0]->occurrences);
        self::assertTrue($resolution->isClean());
    }

    public function testASingleReferenceCountsOneOccurrenceByDefault(): void
    {
        self::assertSame(1, new Problem(ProblemKind::MissingNote, 'a', 'b')->occurrences);
    }

    public function testAReferenceInsideANoteIsReportedAndNumbersNothing(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([
            F::paragraph('p', F::reference('a')),
            F::note('n', 'a', 'A'.F::reference('b')),
            F::note('m', 'b', 'B'),
        ]));

        $problems = $resolution->problemsOf(ProblemKind::ReferenceInNote);
        self::assertCount(1, $problems);
        self::assertSame('b', $problems[0]->noteId);
        self::assertSame('n', $problems[0]->blockId);
        self::assertCount(1, $resolution->notes);
    }

    public function testIdentifiersOfDigitsStayTexts(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([
            F::paragraph('p', F::reference('12')),
            F::note('n', '12', 'Twelve'),
            F::note('m', '13', 'Thirteen'),
        ]));

        self::assertSame('12', $resolution->note('12')?->id);
        self::assertSame('13', $resolution->problemsOf(ProblemKind::UnreferencedNote)[0]->noteId);
        self::assertCount(1, new NoteResolver()->resolve(F::document([F::paragraph('p', F::reference('12').F::reference('12')), F::note('n', '12', 'T')]))->problemsOf(ProblemKind::RepeatedReference));
    }

    public function testBlocksThatWereNotNormalizedAreRead(): void
    {
        $resolution = new NoteResolver()->resolve(new Document([
            new Block('p', 'paragraph', ['text' => F::reference('a').F::reference('b')]),
            new Block('n1', 'footnote', ['id' => 'a', 'text' => 12]),
            new Block('n2', 'footnote', ['id' => 5, 'text' => 'no identifier']),
            new Block('n3', 'footnote', ['text' => 'none at all']),
        ]));

        self::assertSame('', $resolution->note('a')?->html);
        self::assertCount(1, $resolution->problemsOf(ProblemKind::MissingNote));
    }

    public function testTheProblemsComeInAKnownOrder(): void
    {
        $resolution = new NoteResolver()->resolve(F::document([
            F::paragraph('p', F::reference('ghost').F::reference('a').F::reference('a')),
            F::note('n1', 'a', 'A'),
            F::note('n2', 'a', 'A again'),
            F::note('n3', 'alone', 'Alone'),
        ]));

        self::assertSame(
            [ProblemKind::DuplicateId, ProblemKind::MissingNote, ProblemKind::RepeatedReference, ProblemKind::UnreferencedNote],
            array_map(static fn (Problem $problem): ProblemKind => $problem->kind, $resolution->problems),
        );
        self::assertSame(ProblemKind::UnreferencedNote, $resolution->problemsOf(ProblemKind::UnreferencedNote)[0]->kind);
    }

    /**
     * @return list<string>
     */
    private function labels(Document $document, NoteOptions $options): array
    {
        return array_map(static fn (Note $note): string => $note->label, new NoteResolver($options)->resolve($document)->notes);
    }
}
