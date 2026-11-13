<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Tests\Unit;

use Phil\FootnotesSidenotes\NoteResolver;
use Phil\FootnotesSidenotes\Placement;
use Phil\FootnotesSidenotes\ProblemKind;
use Phil\FootnotesSidenotes\Stylesheet;
use Phil\PagedMediaBundle\Infrastructure\Notes\NotesPage;
use Phil\PagedMediaBundle\Infrastructure\Notes\PrintedNotes;
use Phil\Ujo\Document;
use Phil\Ujo\Html\HtmlRenderer;
use Phil\Ujo\Ujo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrintedNotes::class)]
#[CoversClass(NotesPage::class)]
final class PrintedNotesTest extends TestCase
{
    public function testTheNotesGoToTheFootOfThePageWithBothSheets(): void
    {
        $printed = $this->printed()->page($this->document());

        self::assertStringContainsString('isPageNote', $printed->page->body);
        self::assertStringContainsString('data-note-label="1"', $printed->page->body);
        self::assertStringNotContainsString('isNotesList', $printed->page->body);
        self::assertSame([Stylesheet::Screen->contents(), Stylesheet::Print->contents()], $printed->page->stylesheets);
        self::assertSame('en', $printed->page->language);
        self::assertNull($printed->page->title);
        self::assertCount(1, $printed->resolution->notes);
    }

    public function testTheEngineMayNumberEachPage(): void
    {
        $printed = $this->printed()->page($this->document(), 'fr', countPerPage: true, title: 'Livre');

        self::assertSame([Stylesheet::Screen->contents(), Stylesheet::Print->contents(), Stylesheet::PrintPerPage->contents()], $printed->page->stylesheets);
        self::assertSame('fr', $printed->page->language);
        self::assertSame('Livre', $printed->page->title);
    }

    public function testAnotherPlacementIsThePrintersChoiceInTheLanguageOfThePage(): void
    {
        $printed = $this->printed()->page($this->document(), 'fr-CA', Placement::EndOfDocument);

        self::assertStringContainsString('isNotesList', $printed->page->body);
        self::assertStringNotContainsString('isPageNote', $printed->page->body);
        self::assertStringContainsString('Retour au renvoi', $printed->page->body);
    }

    public function testTheProblemsFoundAreGivenBack(): void
    {
        $printed = $this->printed()->page($this->document('nope'));

        self::assertCount(1, $printed->resolution->problemsOf(ProblemKind::MissingNote));
    }

    private function printed(): PrintedNotes
    {
        return new PrintedNotes(new NoteResolver(), HtmlRenderer::default());
    }

    private function document(string $reference = 'n1'): Document
    {
        return new Ujo()->decode((string) json_encode(['blocks' => [
            ['id' => 'a', 'type' => 'paragraph', 'data' => ['text' => 'Text<sup data-footnote="'.$reference.'"></sup>']],
            ['id' => 'b', 'type' => 'footnote', 'data' => ['id' => 'n1', 'text' => 'The note']],
        ]]));
    }
}
