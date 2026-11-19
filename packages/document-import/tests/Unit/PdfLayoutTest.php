<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Pdf\PdfLayout;
use Phil\DocumentImport\Internal\Pdf\PdfLine;
use Phil\DocumentImport\Internal\Pdf\PdfRun;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaFolder;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The lines of a PDF written as blocks: the body size and the heading sizes, bold lines alone, list items and their
 * levels, paragraphs over lines and pages, words cut by a hyphen, marks and images. Each line is given as its page,
 * start, baseline, size and runs — a run being its text, bold, italic, rise and size.
 */
#[CoversClass(PdfLayout::class)]
#[CoversClass(PdfLine::class)]
final class PdfLayoutTest extends TestCase
{
    /**
     * @return array<string, array{list<list<mixed>>, string}>
     */
    public static function layouts(): array
    {
        $b = static fn (int $page, float $y, string $text = 'Body text that is long enough to weigh as the body of the page.'): array => [$page, 72.0, $y, 10.0, $text];
        $ten = str_repeat('abcdefghij', 10);
        $bodies = static fn (float $from, int $count): array => array_map(static fn (int $i): array => [0, 72.0, $from - 12 * $i, 10.0, $ten], range(0, $count - 1));
        $cases = [
            'sizes' => [[0, 72.0, 760.0, 12.0, 'Twelve'], $b(0, 740), $b(0, 728), [0, 72.0, 700.0, 11.5, 'Eleven and a half'], $b(0, 680), [0, 72.0, 660.0, 11.0, 'Eleven'], $b(0, 640), [0, 72.0, 620.0, 9.0, 'Nine'], $b(0, 600)],
            'rounded sizes' => [[0, 72.0, 760.0, 11.3, 'Rounded up'], $b(0, 740), $b(0, 728), [0, 72.0, 700.0, 11.74, 'Also eleven and a half'], $b(0, 680)],
            'levels' => [[0, 72.0, 780.0, 24.0, 'L1'], [0, 72.0, 750.0, 22.0, 'L2'], [0, 72.0, 720.0, 20.0, 'L3'], [0, 72.0, 690.0, 18.0, 'L4'], [0, 72.0, 660.0, 16.0, 'L5'], [0, 72.0, 630.0, 14.0, 'L6'], [0, 72.0, 600.0, 13.0, 'L7'], $b(0, 580), $b(0, 568), $b(0, 556), $b(0, 544), $b(0, 532), $b(0, 520)],
            'weights' => [[0, 72.0, 780.0, 12.0, str_repeat('h', 100)], [0, 72.0, 750.0, 13.0, str_repeat('i', 101)], ...$bodies(700, 4)],
            'long headings' => [[0, 72.0, 780.0, 12.0, str_repeat('é', 200)], [0, 72.0, 750.0, 14.0, str_repeat('é', 201)], ...$bodies(700, 10)],
            'alone' => [[0, 72.0, 780.0, 12.0, 'Title'], ...$bodies(760, 4), [0, 72.0, 700.0, 10.0, 'Section', true], ...$bodies(680, 2), [0, 72.0, 640.0, 10.0, 'Ends with a period.', true], ...$bodies(620, 2), [0, 72.0, 580.0, 10.0, str_repeat('é', 100), true], ...$bodies(560, 2), [0, 72.0, 520.0, 10.0, str_repeat('é', 101), true], ...$bodies(500, 2), [0, 72.0, 460.0, 10.0, 'Two bold', true], [0, 72.0, 448.0, 10.0, 'lines', true], ...$bodies(430, 2), [0, 72.0, 390.0, 10.0, 'Plain'], ...$bodies(370, 2), [0, 72.0, 330.0, 10.0, [['Half ', true], ['bold', false]]], ...$bodies(310, 2), [0, 72.0, 270.0, 10.0, [['Bold ', true], [' ', false]]], ...$bodies(250, 2)],
            'alone without headings' => [...$bodies(760, 2), [0, 72.0, 720.0, 10.0, 'Section', true], ...$bodies(700, 2)],
            'lists' => [[0, 72.0, 760.0, 10.0, '• one'], [0, 72.0, 748.0, 10.0, '- two'], [0, 90.0, 736.0, 10.0, '* deeper'], [0, 92.0, 724.0, 10.0, '· same'], [0, 96.0, 712.0, 10.0, '– deeper again'], [0, 72.0, 700.0, 10.0, '•   back'], [0, 74.0, 688.0, 10.0, 'continued'], [0, 72.0, 676.0, 10.0, 'not continued']],
            'numbered' => [[0, 72.0, 760.0, 10.0, '1. first'], [0, 72.0, 748.0, 10.0, '2) second'], [0, 72.0, 736.0, 10.0, '(c) third'], [0, 72.0, 724.0, 10.0, 'iv. fourth'], [0, 72.0, 712.0, 10.0, '• bullet'], [0, 72.0, 700.0, 10.0, '5. fifth'], [0, 72.0, 680.0, 10.0, 'IMAGE'], [0, 72.0, 660.0, 10.0, '6. after image']],
            'deep list' => array_map(static fn (int $i): array => [0, 72.0 + 10 * $i, 760.0 - 12 * $i, 10.0, '• level '.$i], range(0, 10)),
            'back by three' => [[0, 72.0, 760.0, 10.0, '• a'], [0, 82.0, 748.0, 10.0, '• b'], [0, 79.0, 736.0, 10.0, '• c'], [0, 78.9, 724.0, 10.0, '• d'], [0, 85.0, 712.0, 10.0, '• e'], [0, 88.0, 700.0, 10.0, '• f'], [0, 88.1, 688.0, 10.0, '• g'], [0, 88.1, 676.0, 10.0, 'h'], [0, 89.1, 664.0, 10.0, 'i'], [0, 89.2, 652.0, 10.0, 'j']],
            'gaps' => [[0, 72.0, 760.0, 10.0, 'one'], [0, 72.0, 742.0, 10.0, 'eighteen'], [0, 72.0, 723.5, 10.0, 'more'], [0, 72.0, 711.5, 12.0, 'bigger by a fifth'], [0, 72.0, 699.5, 14.5, 'bigger by more'], [0, 72.0, 720.0, 14.5, 'above']],
            'pages' => [[0, 72.0, 100.0, 10.0, 'running on'], [1, 72.0, 760.0, 10.0, 'the next page'], [1, 72.0, 100.0, 10.0, 'ends here'], [2, 72.0, 760.0, 10.0, 'Capital'], [2, 72.0, 100.0, 10.0, 'stops.'], [3, 72.0, 760.0, 10.0, 'lower'], [3, 72.0, 100.0, 10.0, 'quoted»'], [4, 72.0, 760.0, 10.0, 'lower again'], [4, 72.0, 100.0, 10.0, 'spaced: '], [5, 72.0, 760.0, 10.0, ' éclair'], [5, 72.0, 100.0, 10.0, 'letter Ż'], [6, 72.0, 760.0, 10.0, 'lower'], [6, 72.0, 100.0, 10.0, 'accented'], [7, 72.0, 760.0, 10.0, 'été']],
            'hyphens' => [[0, 72.0, 760.0, 10.0, 'exam-'], [0, 72.0, 748.0, 10.0, 'ple Exam-'], [0, 72.0, 736.0, 10.0, 'Ple 3-'], [0, 72.0, 724.0, 10.0, 'x été-'], [0, 72.0, 712.0, 10.0, 'été end '], [0, 72.0, 700.0, 10.0, 'next'], [0, 72.0, 688.0, 10.0, ' spaced'], [0, 72.0, 676.0, 10.0, 'a-b'], [0, 72.0, 664.0, 10.0, 'c wor-'], [0, 72.0, 652.0, 10.0, ' ld']],
            'headings over lines' => [[0, 72.0, 780.0, 12.0, 'A heading'], [0, 72.0, 766.0, 12.0, 'on two lines'], [0, 72.0, 740.0, 12.6, 'Another size'], ...$bodies(720, 6)],
            'marks' => [[0, 72.0, 760.0, 10.0, [['bold ', true], ['both ', true, true], ['italic ', false, true], ['x', false], ['2', false, false, 3, 6]]], [0, 72.0, 748.0, 10.0, [['  many   spaces  ', false]]]],
            'rounded alone' => [[0, 72.0, 760.0, 11.3, 'Rounded up'], $b(0, 740), $b(0, 728), $b(0, 716)],
            'weight edge' => [[0, 72.0, 780.0, 12.0, str_repeat('h', 101)], ...$bodies(700, 4), [0, 72.0, 640.0, 10.0, 'x']],
            'fractional body' => [[0, 72.0, 780.0, 13.0, 'Heading'], [0, 72.0, 760.0, 10.5, $ten], [0, 72.0, 748.0, 10.5, $ten]],
            'only an image' => [[0, 72.0, 700.0, 10.0, 'IMAGE']],
            'heading sizes half apart' => [[0, 72.0, 780.0, 12.0, 'One heading'], [0, 72.0, 766.0, 12.5, 'over two sizes'], [0, 72.0, 740.0, 13.6, 'Next'], [0, 72.0, 724.0, 13.0, 'apart'], ...$bodies(700, 8)],
            'numbered heading' => [[0, 72.0, 780.0, 14.0, '1. Introduction'], ...$bodies(760, 4)],
            'punctuation inside' => [[0, 72.0, 100.0, 10.0, 'e.g. so'], [1, 72.0, 760.0, 10.0, 'lower']],
            'same baseline' => [[0, 72.0, 700.0, 10.0, 'left'], [0, 300.0, 700.0, 10.0, 'right']],
            'item continuation' => [[0, 72.0, 760.0, 10.0, '• item'], [0, 73.0, 748.0, 10.0, 'at one point'], [0, 72.0, 736.0, 10.0, '• item'], [0, 72.5, 724.0, 10.0, 'at half a point'], [0, 72.0, 712.0, 10.0, '• item'], [0, 73.5, 700.0, 10.0, 'past one point']],
            'indent kept' => [[0, 72.0, 760.0, 10.0, '• a'], [0, 82.0, 748.0, 10.0, '• b'], [0, 79.0, 736.0, 10.0, '• c'], [0, 84.0, 724.0, 10.0, '• d']],
            'spaced bullet' => [[0, 72.0, 760.0, 10.0, ' • spaced bullet'], [0, 72.0, 748.0, 10.0, '• - nested marker']],
            'alone edges' => [[0, 72.0, 780.0, 12.0, 'Title'], ...$bodies(760, 4), [0, 72.0, 700.0, 10.0, str_repeat('é', 100).'   ', true], ...$bodies(680, 2), [0, 72.0, 640.0, 10.0, 'One, two', true], ...$bodies(620, 2)],
            'bold end of line' => [[0, 72.0, 760.0, 10.0, [['end ', true]]], [0, 72.0, 748.0, 10.0, 'next']],
            'heading levels rounded' => [[0, 72.0, 780.0, 14.0, 'A'], [0, 72.0, 750.0, 12.8, 'B'], [0, 72.0, 720.0, 12.2, 'C'], ...$bodies(700, 4)],
            'image' => [[0, 72.0, 760.0, 10.0, 'Before'], [0, 72.0, 700.0, 10.0, 'IMAGE'], [0, 72.0, 690.0, 10.0, 'after']],
        ];
        $expected = [
            'sizes' => '[["header",{"text":"Twelve","level":1}],["paragraph",{"text":"Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page."}],["header",{"text":"Eleven and a half","level":2}],["paragraph",{"text":"Body text that is long enough to weigh as the body of the page."}],["paragraph",{"text":"Eleven"}],["paragraph",{"text":"Body text that is long enough to weigh as the body of the page."}],["paragraph",{"text":"Nine"}],["paragraph",{"text":"Body text that is long enough to weigh as the body of the page."}]]',
            'rounded sizes' => '[["header",{"text":"Rounded up","level":1}],["paragraph",{"text":"Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page."}],["header",{"text":"Also eleven and a half","level":1}],["paragraph",{"text":"Body text that is long enough to weigh as the body of the page."}]]',
            'levels' => '[["header",{"text":"L1","level":1}],["header",{"text":"L2","level":2}],["header",{"text":"L3","level":3}],["header",{"text":"L4","level":4}],["header",{"text":"L5","level":5}],["header",{"text":"L6","level":6}],["header",{"text":"L7","level":6}],["paragraph",{"text":"Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page."}]]',
            'weights' => '[["header",{"text":"hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh","level":1}],["paragraph",{"text":"iiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii"}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'long headings' => '[["header",{"text":"éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé","level":1}],["paragraph",{"text":"ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé"}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'alone' => '[["header",{"text":"Title","level":1}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["header",{"text":"Section","level":2}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["paragraph",{"text":"<b>Ends with a period.</b>"}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["header",{"text":"éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé","level":2}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["paragraph",{"text":"<b>ééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé</b>"}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["paragraph",{"text":"<b>Two bold lines</b> abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["paragraph",{"text":"Plain"}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["paragraph",{"text":"<b>Half </b>bold"}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["header",{"text":"Bold ","level":2}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'alone without headings' => '[["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["paragraph",{"text":"<b>Section</b>"}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'lists' => '[["list",{"style":"unordered","meta":[],"items":[{"content":"one","meta":[],"items":[]},{"content":"two","meta":[],"items":[{"content":"deeper","meta":[],"items":[]},{"content":"same","meta":[],"items":[{"content":"deeper again","meta":[],"items":[]}]}]},{"content":"back continued","meta":[],"items":[]}]}],["paragraph",{"text":"not continued"}]]',
            'numbered' => '[["list",{"style":"ordered","meta":[],"items":[{"content":"first","meta":[],"items":[]},{"content":"second","meta":[],"items":[]},{"content":"third","meta":[],"items":[]},{"content":"fourth","meta":[],"items":[]}]}],["list",{"style":"unordered","meta":[],"items":[{"content":"bullet","meta":[],"items":[]}]}],["list",{"style":"ordered","meta":[],"items":[{"content":"fifth","meta":[],"items":[]}]}],["image",{"file":{"url":"media/p.png"}}],["list",{"style":"ordered","meta":[],"items":[{"content":"after image","meta":[],"items":[]}]}]]',
            'deep list' => '[["list",{"style":"unordered","meta":[],"items":[{"content":"level 0","meta":[],"items":[{"content":"level 1","meta":[],"items":[{"content":"level 2","meta":[],"items":[{"content":"level 3","meta":[],"items":[{"content":"level 4","meta":[],"items":[{"content":"level 5","meta":[],"items":[{"content":"level 6","meta":[],"items":[{"content":"level 7","meta":[],"items":[{"content":"level 8","meta":[],"items":[]},{"content":"level 9","meta":[],"items":[]},{"content":"level 10","meta":[],"items":[]}]}]}]}]}]}]}]}]}]}]]',
            'back by three' => '[["list",{"style":"unordered","meta":[],"items":[{"content":"a","meta":[],"items":[{"content":"b","meta":[],"items":[]},{"content":"c","meta":[],"items":[]},{"content":"d","meta":[],"items":[{"content":"e","meta":[],"items":[]},{"content":"f","meta":[],"items":[{"content":"g","meta":[],"items":[]}]}]}]}]}],["paragraph",{"text":"h i j"}]]',
            'gaps' => '[["paragraph",{"text":"one eighteen"}],["paragraph",{"text":"more bigger by a fifth"}],["paragraph",{"text":"bigger by more"}],["paragraph",{"text":"above"}]]',
            'pages' => '[["paragraph",{"text":"running on the next page"}],["paragraph",{"text":"ends here"}],["paragraph",{"text":"Capital"}],["paragraph",{"text":"stops."}],["paragraph",{"text":"lower"}],["paragraph",{"text":"quoted»"}],["paragraph",{"text":"lower again"}],["paragraph",{"text":"spaced:"}],["paragraph",{"text":"éclair"}],["paragraph",{"text":"letter Ż lower"}],["paragraph",{"text":"accented été"}]]',
            'hyphens' => '[["paragraph",{"text":"example Exam- Ple 3- x étéété end next spaced a-b c wor- ld"}]]',
            'headings over lines' => '[["header",{"text":"A heading on two lines","level":2}],["header",{"text":"Another size","level":1}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'marks' => '[["paragraph",{"text":"<b>bold <i>both </i></b><i>italic </i>x<sup>2</sup> many spaces"}]]',
            'rounded alone' => '[["header",{"text":"Rounded up","level":1}],["paragraph",{"text":"Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page. Body text that is long enough to weigh as the body of the page."}]]',
            'weight edge' => '[["paragraph",{"text":"hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh"}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["paragraph",{"text":"x"}]]',
            'fractional body' => '[["header",{"text":"Heading","level":1}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'only an image' => '[["image",{"file":{"url":"media/p.png"}}]]',
            'heading sizes half apart' => '[["header",{"text":"One heading over two sizes","level":4}],["header",{"text":"Next","level":1}],["header",{"text":"apart","level":2}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'numbered heading' => '[["header",{"text":"1. Introduction","level":1}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'punctuation inside' => '[["paragraph",{"text":"e.g. so lower"}]]',
            'same baseline' => '[["paragraph",{"text":"left"}],["paragraph",{"text":"right"}]]',
            'item continuation' => '[["list",{"style":"unordered","meta":[],"items":[{"content":"item","meta":[],"items":[]}]}],["paragraph",{"text":"at one point"}],["list",{"style":"unordered","meta":[],"items":[{"content":"item","meta":[],"items":[]}]}],["paragraph",{"text":"at half a point"}],["list",{"style":"unordered","meta":[],"items":[{"content":"item past one point","meta":[],"items":[]}]}]]',
            'indent kept' => '[["list",{"style":"unordered","meta":[],"items":[{"content":"a","meta":[],"items":[{"content":"b","meta":[],"items":[]},{"content":"c","meta":[],"items":[]},{"content":"d","meta":[],"items":[]}]}]}]]',
            'spaced bullet' => '[["list",{"style":"unordered","meta":[],"items":[{"content":"spaced bullet","meta":[],"items":[]},{"content":"- nested marker","meta":[],"items":[]}]}]]',
            'alone edges' => '[["header",{"text":"Title","level":1}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["header",{"text":"éééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééééé ","level":2}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}],["header",{"text":"One, two","level":2}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'bold end of line' => '[["paragraph",{"text":"<b>end </b>next"}]]',
            'heading levels rounded' => '[["header",{"text":"A","level":1}],["header",{"text":"B","level":2}],["header",{"text":"C","level":3}],["paragraph",{"text":"abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij abcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghijabcdefghij"}]]',
            'image' => '[["paragraph",{"text":"Before"}],["image",{"file":{"url":"media/p.png"}}],["paragraph",{"text":"after"}]]',
        ];

        return array_map(static fn (string $name): array => [$cases[$name], $expected[$name]], array_combine(array_keys($cases), array_keys($cases)));
    }

    /**
     * @param list<list<mixed>> $lines
     */
    #[DataProvider('layouts')]
    public function testLinesBecomeBlocks(array $lines, string $blocks): void
    {
        self::assertSame($blocks, json_encode($this->layout($lines), \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR));
    }

    public function testRunsGatherIntoLines(): void
    {
        $runs = [
            new PdfRun('a', 0.0, 5.0, 100.0, 10.0),
            // Half a size above, a gap of 0.15 of a size: the same line, no space.
            new PdfRun('b', 6.5, 11.5, 105.0, 10.0),
            // A gap of more: a space.
            new PdfRun('c', 13.1, 18.1, 100.0, 10.0),
            // Smaller by more than 0.85 and raised by more than 0.15 of the line's size: an exponent.
            new PdfRun('2', 18.1, 20.0, 101.6, 8.4),
            // As small, raised by 0.15: not an exponent; small on the baseline: not either.
            new PdfRun('3', 20.0, 22.0, 101.5, 8.4),
            new PdfRun('4', 22.0, 24.0, 100.0, 6.0),
            // Smaller by 0.85 exactly: not an exponent.
            new PdfRun('5', 24.0, 26.0, 103.0, 8.5),
            // Past half a size above: another line; a run of spaces alone starts none.
            new PdfRun('d', 0.0, 5.0, 105.1, 10.0),
            new PdfRun('   ', 0.0, 5.0, 50.0, 10.0),
            new PdfRun('e', 0.0, 5.0, 30.0, 10.0),
        ];
        $lines = PdfLayout::lines($runs, 3);

        self::assertSame(['ab c2345', 'd', 'e'], array_map(static fn (PdfLine $line): string => $line->text(), $lines));
        self::assertSame([false, false, false, false, true, false, false, false], array_map(static fn (array $run): bool => $run[1], $lines[0]->runs));
        self::assertSame([3, 3, 3], array_map(static fn (PdfLine $line): int => $line->page, $lines));

        $out = new Builder(new MediaFolder(), new SvgSanitizer());
        new PdfLayout($out)->write($lines);

        self::assertSame([['text' => 'ab c<sup>2</sup>345'], ['text' => 'd'], ['text' => 'e']], array_map(static fn (Block $block): array => $block->data, $out->document()->blocks));
    }

    /**
     * @param list<list<mixed>> $specs
     *
     * @return list<array{string, array<array-key, mixed>}>
     */
    private function layout(array $specs): array
    {
        $out = new Builder(new MediaFolder(), new SvgSanitizer());
        $lines = [];

        foreach ($specs as $spec) {
            $page = (int) $this->scalar($spec[0]);
            [$x, $y, $size] = [(float) $this->scalar($spec[1]), (float) $this->scalar($spec[2]), (float) $this->scalar($spec[3])];
            $runs = $spec[4];

            if ('IMAGE' === $runs) {
                $media = new Media('p.png', "\x89PNG\r\n\x1A\n".str_repeat("\0", 20));
                $lines[] = new PdfLine($page, $x, $y, new PdfRun('', $x, $x, $y, $size, image: $media), $media);

                continue;
            }

            $line = null;
            $at = $x;

            foreach (\is_array($runs) ? $runs : [[$runs, $spec[5] ?? false]] as $run) {
                self::assertIsArray($run);
                $text = (string) $this->scalar($run[0]);
                $width = 5.0 * mb_strlen($text);
                $made = new PdfRun($text, $at, $at + $width, $y + (float) $this->scalar($run[3] ?? 0), (float) $this->scalar($run[4] ?? $size), true === ($run[1] ?? false), true === ($run[2] ?? false));
                $at += $width;
                $line instanceof PdfLine ? $line->add($made) : $line = new PdfLine($page, $x, $y, $made);
            }

            self::assertInstanceOf(PdfLine::class, $line);
            $lines[] = $line;
        }

        new PdfLayout($out)->write($lines);

        return array_map(static fn (Block $block): array => [$block->type, $block->data], $out->document()->blocks);
    }

    private function scalar(mixed $value): bool|float|int|string
    {
        self::assertIsScalar($value);

        return $value;
    }
}
