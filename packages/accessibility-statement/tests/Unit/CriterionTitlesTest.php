<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\CriterionTitles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CriterionTitles::class)]
final class CriterionTitlesTest extends TestCase
{
    public function testEverySuccessCriterionHasItsOfficialFrenchTitle(): void
    {
        $titles = CriterionTitles::french();

        self::assertSame(array_map(static fn (SuccessCriterion $criterion): string => $criterion->value, SuccessCriterion::cases()), array_keys($titles));
        self::assertSame('Contenu non textuel', $titles['1.1.1']);
        self::assertSame('Contraste (minimum)', $titles['1.4.3']);
        self::assertSame('Taille de la cible (minimum)', $titles['2.5.8']);
        self::assertArrayNotHasKey('4.1.1', $titles);
    }

    public function testTheSourceIsNamed(): void
    {
        $notice = (string) file_get_contents(\dirname(CriterionTitles::FRENCH).'/NOTICE.md');

        self::assertStringContainsString('https://www.w3.org/Translations/WCAG22-fr-20260922/', $notice);
        self::assertStringContainsString('https://www.w3.org/copyright/document-license/', $notice);
    }
}
