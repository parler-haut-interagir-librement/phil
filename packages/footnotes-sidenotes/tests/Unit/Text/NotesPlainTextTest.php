<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit\Text;

use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Tests\Unit\DocumentFactory as F;
use Phil\FootnotesSidenotes\Text\NotesPlainText;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Text\PlainText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotesPlainText::class)]
final class NotesPlainTextTest extends TestCase
{
    public function testReferencesAreMarkedAndNotesFollowTheText(): void
    {
        $document = F::document([
            F::paragraph('p', 'One'.F::reference('a', '<a href="#x">9</a>').' two'.F::reference('b').' and'.F::reference('a')),
            F::note('na', 'a', "First <b>note</b>\nwith a line"),
            F::note('nb', 'b', 'Second'.F::reference('a').' note'),
        ]);

        self::assertSame("One[1] two[2] and[1]\n\n[1] First note with a line\n[2] Second note", new NotesPlainText()->convert($document, new NoteResolver()->resolve($document)));
    }

    public function testNestedBlocksAndListsAreMarked(): void
    {
        $document = F::document([
            ['id' => 'wide', 'type' => 'wide', 'data' => ['blocks' => [F::paragraph('p', 'In'.F::reference('a'))]]],
            F::note('n', 'a', 'A'),
        ]);

        self::assertSame("In[1]\n\n[1] A", new NotesPlainText()->convert($document, new NoteResolver()->resolve($document)));
    }

    public function testACodeBlockKeepsItsText(): void
    {
        $document = F::document([['id' => 'c', 'type' => 'code', 'data' => ['code' => F::reference('a')]]]);

        self::assertSame(F::reference('a'), new NotesPlainText()->convert($document, new NoteResolver()->resolve($document)));
    }

    public function testAReferenceWithoutNoteIsLeftOut(): void
    {
        $document = F::document([F::paragraph('p', 'x'.F::reference('ghost').'y')]);

        self::assertSame('xy', new NotesPlainText()->convert($document, new NoteResolver()->resolve($document)));
    }

    public function testADocumentWithoutTextGivesItsNotesAloneOrNothing(): void
    {
        self::assertSame('', new NotesPlainText()->convert(new Document(), new NoteResolver()->resolve(new Document())));

        $document = new Document([new Block('n', 'footnote', ['id' => 'a', 'text' => 'A'])]);
        self::assertSame('', new NotesPlainText()->convert($document, new NoteResolver()->resolve($document)));
    }

    public function testValuesThatAreNotTextsStay(): void
    {
        $document = F::document([F::heading('h', 3, 'T'.F::reference('a')), F::note('n', 'a', 'A')]);

        self::assertSame("T[1]\n\n[1] A", new NotesPlainText()->convert($document, new NoteResolver()->resolve($document)));
    }

    public function testTheConversionsOfTheCallerAreKept(): void
    {
        $document = F::document([F::paragraph('p', 'x'), ['id' => 'd', 'type' => 'delimiter', 'data' => []]]);
        $text = new PlainText()->with('delimiter', static fn (): string => '---');

        self::assertSame("x\n\n---", new NotesPlainText($text)->convert($document, new NoteResolver()->resolve($document)));
    }
}
