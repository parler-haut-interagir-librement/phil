<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Application;

use Phil\TranslationBundle\Application\Progress;
use Phil\TranslationMemory\Segment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Progress::class)]
final class ProgressTest extends TestCase
{
    public function testCountsTheSegmentsInEachState(): void
    {
        $progress = Progress::of([
            new Segment('a', 'A'),
            new Segment('b', 'B')->suggest('b'),
            new Segment('c', 'C')->translate('c'),
            new Segment('d', 'D')->translate('d')->review(),
            new Segment('e', 'E')->translate('e')->withSource('E!'),
            new Segment('f', 'F')->translate('f'),
        ]);

        self::assertSame(['untranslated' => 1, 'suggested' => 1, 'translated' => 2, 'reviewed' => 1, 'stale' => 1], $progress->counts);
        self::assertSame([6, 3, 50], [$progress->total(), $progress->usable(), $progress->percent()]);
    }

    public function testRoundsDownAndCountsAnEmptyDocumentAsDone(): void
    {
        self::assertSame(66, Progress::of([new Segment('a', 'A')->translate('a'), new Segment('b', 'B')->translate('b'), new Segment('c', 'C')])->percent());
        self::assertSame([0, 100], [Progress::of([])->total(), Progress::of([])->percent()]);
    }
}
