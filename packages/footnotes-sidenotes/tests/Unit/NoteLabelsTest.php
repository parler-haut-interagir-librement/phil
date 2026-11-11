<?php

declare(strict_types=1);

namespace Phil\FootnotesSidenotes\Tests\Unit;

use Phil\FootnotesSidenotes\NoteLabels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(NoteLabels::class)]
final class NoteLabelsTest extends TestCase
{
    public function testTheEnglishNames(): void
    {
        $labels = NoteLabels::english();

        self::assertSame('Notes', $labels->title);
        self::assertSame('Note iv', $labels->referenceName('iv'));
        self::assertSame('Back to reference iv', $labels->backlinkName('iv', 1, false));
        self::assertSame('Back to reference iv, occurrence 2', $labels->backlinkName('iv', 2, true));
        self::assertSame('Show note iv', $labels->toggleName('iv'));
    }

    public function testTheFrenchNames(): void
    {
        $labels = NoteLabels::french();

        self::assertSame('Notes', $labels->title);
        self::assertSame('Note 3', $labels->referenceName('3'));
        self::assertSame('Retour au renvoi 3', $labels->backlinkName('3', 1, false));
        self::assertSame('Retour au renvoi 3, occurrence 2', $labels->backlinkName('3', 2, true));
        self::assertSame('Afficher la note 3', $labels->toggleName('3'));
    }

    #[TestWith(['fr', 'Retour au renvoi 1'])]
    #[TestWith(['FR-ca', 'Retour au renvoi 1'])]
    #[TestWith(['fr_BE', 'Retour au renvoi 1'])]
    #[TestWith(['en', 'Back to reference 1'])]
    #[TestWith(['de', 'Back to reference 1'])]
    public function testALocaleFindsItsLabels(string $locale, string $expected): void
    {
        self::assertSame($expected, NoteLabels::forLocale($locale)->backlinkName('1', 1, false));
    }
}
