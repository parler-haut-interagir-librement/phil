<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Form\Check;
use Phil\PagedMedia\Pdf\Form\CheckBox;
use Phil\PagedMedia\Pdf\Form\ChoiceField;
use Phil\PagedMedia\Pdf\Form\Fields;
use Phil\PagedMedia\Pdf\Form\FormWriter;
use Phil\PagedMedia\Pdf\Form\RadioButton;
use Phil\PagedMedia\Pdf\Form\RadioGroup;
use Phil\PagedMedia\Pdf\Form\TextField;
use Phil\PagedMedia\Pdf\Form\Widget;
use Phil\PagedMedia\Tests\Unit\Fixture\PdfReader;
use Phil\PagedMedia\Tests\Unit\Fixture\TrueTypeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Document::class)]
#[CoversClass(Fields::class)]
#[CoversClass(FormWriter::class)]
#[CoversClass(Widget::class)]
#[CoversClass(Check::class)]
#[CoversClass(TextField::class)]
#[CoversClass(CheckBox::class)]
#[CoversClass(RadioButton::class)]
#[CoversClass(RadioGroup::class)]
#[CoversClass(ChoiceField::class)]
#[CoversClass(TrueTypeFont::class)]
final class FormTest extends TestCase
{
    public function testAnInteractiveFormHoldsItsFieldsWidgetsAndAppearances(): void
    {
        $reader = new PdfReader($this->sheet()->render());

        self::assertSame('<< /Type /Catalog /Pages 2 0 R /AcroForm << /Fields [13 0 R 14 0 R 15 0 R] /DR << /Font << /F1 3 0 R >> >> >> >>', $reader->object(1));
        self::assertSame('<< /Type /XObject /Subtype /Form /BBox [0 0 200 20] /Resources << /Font << /F1 3 0 R >> >> /Length 141 >>', strstr($reader->object(4), "\nstream\n", true));
        self::assertSame("q 1 1 0.8 rg 0 0 200 20 re f Q\nq 0 G 1 w 0.5 0.5 199 19 re S Q\n/Tx BMC\nq 1 1 198 18 re W n\nBT /F1 10 Tf 0 g 2 6.5 Td (Ann \\(B\\)) Tj ET\nQ\nEMC\n", PdfReader::data($reader->object(4)));
        self::assertSame("q 0 G 1.25 w 1 J 1 j 2.2 5.2 m 4.2 2.8 l 7.8 7.4 l S Q\n", PdfReader::data($reader->object(5)));
        self::assertSame('', PdfReader::data($reader->object(6)));
        self::assertSame("q 0 g 7.5 5 m 7.5 6.38 6.38 7.5 5 7.5 c 3.62 7.5 2.5 6.38 2.5 5 c 2.5 3.62 3.62 2.5 5 2.5 c 6.38 2.5 7.5 3.62 7.5 5 c f Q\n", PdfReader::data($reader->object(7)));
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents 11 0 R /Annots [13 0 R 14 0 R 16 0 R 17 0 R] >>', $reader->object(12));
        self::assertSame('<< /Type /Annot /Subtype /Widget /FT /Tx /T (name) /TU (Nom) /Ff 2 /V (Ann \\(B\\)) /DA (/F1 10 Tf 0 g) /MaxLen 20 /MK << /BC [0] /BG [1 1 0.8] >> /Rect [72 680 272 700] /F 4 /P 12 0 R /AP << /N 4 0 R >> >>', $reader->object(13));
        self::assertSame('<< /Type /Annot /Subtype /Widget /FT /Btn /T (feat #1) /V /Yes /Rect [72 530 82 540] /F 4 /P 12 0 R /AP << /N << /Yes 5 0 R /Off 6 0 R >> >> /AS /Yes >>', $reader->object(14));
        self::assertSame('<< /FT /Btn /T (size) /Ff 49153 /V /M#2FL /Kids [16 0 R 17 0 R] >>', $reader->object(15));
        self::assertSame('<< /Type /Annot /Subtype /Widget /Parent 15 0 R /Rect [72 500 82 510] /F 4 /P 12 0 R /AP << /N << /S 7 0 R /Off 8 0 R >> >> /AS /Off >>', $reader->object(16));
        self::assertSame('<< /Type /Annot /Subtype /Widget /Parent 15 0 R /Rect [92 500 102 510] /F 4 /P 12 0 R /AP << /N << /M#2FL 9 0 R /Off 10 0 R >> >> /AS /M#2FL >>', $reader->object(17));
        self::assertSame('', $reader->contents());
    }

    public function testAFlattenedFormIsDrawnIntoItsPageWithTheStateShown(): void
    {
        $reader = new PdfReader($this->sheet()->render(flattenForm: true));

        self::assertSame('<< /Type /Catalog /Pages 2 0 R >>', $reader->object(1));
        self::assertStringNotContainsString('/Annot', $reader->pdf);
        self::assertSame("q 1 0 0 1 72 680 cm /Fm1_1 Do Q\nq 1 0 0 1 72 530 cm /Fm2_1 Do Q\nq 1 0 0 1 72 500 cm /Fm3_1 Do Q\nq 1 0 0 1 92 500 cm /Fm3_2 Do Q\n", $reader->contents());
        self::assertSame('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> /XObject << /Fm1_1 4 0 R /Fm2_1 5 0 R /Fm3_1 6 0 R /Fm3_2 7 0 R >> >> /Contents 8 0 R >>', $reader->object(9));
        self::assertSame("q 0 G 1.25 w 1 J 1 j 2.2 5.2 m 4.2 2.8 l 7.8 7.4 l S Q\n", PdfReader::data($reader->object(5)));
        self::assertSame('', PdfReader::data($reader->object(6)));
        self::assertStringStartsWith('q 0 g 7.5 5 m', PdfReader::data($reader->object(7)));
    }

    public function testFieldsOnSeveralPagesAndImagesShareTheResources(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(10, 10, 'Page one');
        $pdf->addPage();
        $pdf->field(new CheckBox('a', 10, 10));
        $pdf->addPage();

        $interactive = new PdfReader($pdf->render());
        self::assertSame('<< /Type /Pages /Kids [7 0 R 9 0 R 12 0 R] /Count 3 >>', $interactive->object(2));
        self::assertStringEndsWith('/Contents 8 0 R /Annots [10 0 R] >>', $interactive->object(9));
        self::assertStringEndsWith('/Contents 11 0 R >>', $interactive->object(12));
        self::assertStringContainsString('/P 9 0 R', $interactive->object(10));
        self::assertStringContainsString('/AcroForm << /Fields [10 0 R] >>', $interactive->object(1));

        $flat = new PdfReader($pdf->render(true));
        self::assertSame("BT /F1 10 Tf 10 10 Td (Page one) Tj ET\nq 1 0 0 1 10 10 cm /Fm1_1 Do Q\n", $flat->contents());
    }

    public function testATextFieldTakesItsFlagsColoursAndFont(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new TextField('n', 0, 0, 100, 30, '', 12, StandardFont::HelveticaBold, readOnly: true, color: Color::rgb(255, 0, 0)));

        $reader = new PdfReader($pdf->render());
        $widget = $reader->find('/FT /Tx');

        self::assertStringContainsString('/T (n) /Ff 1 /V () /DA (/F1 12 Tf 1 0 0 rg) /Rect [0 0 100 30]', $widget);
        self::assertStringNotContainsString('/MK', $widget);
        self::assertStringNotContainsString('/TU', $widget);
        self::assertStringNotContainsString('/MaxLen', $widget);
        self::assertSame("/Tx BMC\nq 1 1 98 28 re W n\nQ\nEMC\n", PdfReader::data($reader->find('/BBox [0 0 100 30]')));
        self::assertStringContainsString('/BaseFont /Helvetica-Bold', $reader->object(3));
    }

    public function testAMultilineFieldWrapsItsParagraphsAndKeepsTheLinesItsBoxHolds(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new TextField('notes', 0, 0, 60, 40, "one two three\r\nfour\rfive\n\nsix", 10, multiline: true, required: true));

        $reader = new PdfReader($pdf->render());

        self::assertStringContainsString('/Ff 4098 /V <FEFF006F006E0065002000740077006F002000740068007200650065000D000A0066006F00750072000D0066006900760065000A000A007300690078> /DA', $reader->find('/FT /Tx'));
        self::assertSame("/Tx BMC\nq 1 1 58 38 re W n\nBT /F1 10 Tf 0 g 2 28 Td (one two) Tj ET\nBT /F1 10 Tf 0 g 2 16.5 Td (three) Tj ET\nBT /F1 10 Tf 0 g 2 5 Td (four) Tj ET\nQ\nEMC\n", PdfReader::data($reader->find('/BBox [0 0 60 40]')));
    }

    public function testAMultilineFieldInATinyBoxStillShowsItsFirstLine(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new TextField('n', 0, 0, 3, 5, 'ab cd', 10, multiline: true));

        $data = PdfReader::data(new PdfReader($pdf->render())->find('/BBox [0 0 3 5]'));

        self::assertSame("/Tx BMC\nq 1 1 1 3 re W n\nBT /F1 10 Tf 0 g 2 -7 Td (a) Tj ET\nQ\nEMC\n", $data);
    }

    public function testAComboBoxShowsTheLabelOfItsValue(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new ChoiceField('class', 0, 0, 100, 20, ['war' => 'Warrior', 'mag' => 'Mage', '7' => '7'], 'mag', tooltip: 'Class'));

        $reader = new PdfReader($pdf->render());

        self::assertStringContainsString('/FT /Ch /T (class) /TU (Class) /Ff 131072 /Opt [[(war) (Warrior)] [(mag) (Mage)] (7)] /V (mag) /DA (/F1 10 Tf 0 g) /Rect', $reader->find('/FT /Ch'));
        self::assertStringNotContainsString('/I [', $reader->find('/FT /Ch'));
        self::assertSame("/Tx BMC\nq 1 1 98 18 re W n\nBT /F1 10 Tf 0 g 2 6.5 Td (Mage) Tj ET\nQ\nEMC\n", PdfReader::data($reader->find('/BBox [0 0 100 20]')));
    }

    public function testAnEditableComboBoxShowsAValueTypedBeyondItsOptions(): void
    {
        $field = new ChoiceField('c', 0, 0, 100, 20, ['A', 'B'], 'Other', editable: true, required: true);
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field($field);

        $reader = new PdfReader($pdf->render());

        self::assertSame('Other', $field->shown());
        self::assertSame([['A', 'A'], ['B', 'B']], $field->options);
        self::assertStringContainsString('/Ff 393218 /Opt [(A) (B)] /V (Other)', $reader->find('/FT /Ch'));
        self::assertStringContainsString('(Other) Tj', PdfReader::data($reader->find('/BBox [0 0 100 20]')));
    }

    public function testAChoiceWithoutSelectionShowsNothing(): void
    {
        $field = new ChoiceField('c', 0, 0, 100, 20, ['A']);
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field($field);

        $reader = new PdfReader($pdf->render());

        self::assertSame('', $field->shown());
        self::assertStringNotContainsString('/V', $reader->find('/FT /Ch'));
        self::assertSame("/Tx BMC\nq 1 1 98 18 re W n\nQ\nEMC\n", PdfReader::data($reader->find('/BBox [0 0 100 20]')));
    }

    public function testAListBoxShowsTheOptionsItsBoxHoldsAndHighlightsTheSelectedOne(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new ChoiceField('list', 0, 0, 80, 30, ['One', 'Two', 'Three'], 'Two', combo: false, border: Color::black()));

        $reader = new PdfReader($pdf->render());

        self::assertStringContainsString('/FT /Ch /T (list) /Opt [(One) (Two) (Three)] /V (Two) /I [1] /DA (/F1 10 Tf 0 g) /MK << /BC [0] >> /Rect', $reader->find('/FT /Ch'));
        self::assertSame(
            "q 0 G 1 w 0.5 0.5 79 29 re S Q\n/Tx BMC\nq 1 1 78 28 re W n\nBT /F1 10 Tf 0 g 2 18 Td (One) Tj ET\nq 0.6 0.75 0.875 rg 1 5 78 11.5 re f Q\nBT /F1 10 Tf 0 g 2 6.5 Td (Two) Tj ET\nQ\nEMC\n",
            PdfReader::data($reader->find('/BBox [0 0 80 30]')),
        );
    }

    public function testAListBoxMarksItsFirstOptionOrNone(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new ChoiceField('first', 0, 0, 80, 30, ['One', 'Two'], 'One', combo: false));
        $pdf->field(new ChoiceField('none', 0, 40, 80, 30, ['One', 'Two'], combo: false));

        $reader = new PdfReader($pdf->render());

        self::assertStringContainsString('/V (One) /I [0] /DA', $reader->find('/T (first)'));
        self::assertStringContainsString('/Opt [(One) (Two)] /DA', $reader->find('/T (none)'));
    }

    public function testTheFormsFontsAreListedByName(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(0, 0, 'a');
        $pdf->text(0, 0, 'b', font: StandardFont::HelveticaBold);
        $pdf->field(new TextField('bold', 0, 0, 10, 10, font: StandardFont::HelveticaBold));
        $pdf->field(new TextField('regular', 0, 20, 10, 10));
        $pdf->field(new TextField('again', 0, 40, 10, 10));

        self::assertStringContainsString('/DR << /Font << /F1 3 0 R /F2 4 0 R >> >>', new PdfReader($pdf->render())->object(1));
    }

    public function testAListBoxShowsAnOptionThatJustFits(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new ChoiceField('list', 0, 0, 80, 13.5, ['One', 'Two'], combo: false));

        self::assertSame("/Tx BMC\nq 1 1 78 11.5 re W n\nBT /F1 10 Tf 0 g 2 1.5 Td (One) Tj ET\nQ\nEMC\n", PdfReader::data(new PdfReader($pdf->render())->find('/BBox [0 0 80 13.5]')));
    }

    public function testButtonsTakeTheirColoursAndAnUncheckedBoxIsOff(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new CheckBox('c', 0, 0, 8, exportValue: 'Oui é', tooltip: 'Agree', color: Color::rgb(0, 0, 255), border: Color::gray(51), background: Color::gray(255)));
        $pdf->field(new RadioGroup('r', [new RadioButton('a', 20, 0, 8)], required: true, color: Color::gray(102), border: Color::black(), background: Color::gray(255)));

        $reader = new PdfReader($pdf->render());
        $box = $reader->find('/T (c)');

        self::assertStringContainsString('/FT /Btn /T (c) /TU (Agree) /V /Off /MK << /BC [0.2] /BG [1] >> /Rect [0 0 8 8]', $box);
        self::assertStringContainsString('/AP << /N << /Oui#20#C3#A9 ', $box);
        self::assertStringEndsWith('/AS /Off >>', $box);
        self::assertSame('<< /FT /Btn /T (r) /Ff 49154 /V /Off /Kids [11 0 R] >>', $reader->find('/T (r)'));
        self::assertStringContainsString('/Parent 10 0 R /MK << /BC [0] /BG [1] >> /Rect [20 0 28 8]', $reader->object(11));
        self::assertSame("q 1 g 0 0 8 8 re f Q\nq 0.2 G 1 w 0.5 0.5 7 7 re S Q\nq 0 0 1 RG 1 w 1 J 1 j 1.76 4.16 m 3.36 2.24 l 6.24 5.92 l S Q\n", PdfReader::data($reader->object(3)));
        self::assertSame("q 1 g 0 0 8 8 re f Q\nq 0.2 G 1 w 0.5 0.5 7 7 re S Q\n", PdfReader::data($reader->object(4)));
        self::assertSame(
            "q 1 g 8 4 m 8 6.21 6.21 8 4 8 c 1.79 8 0 6.21 0 4 c 0 1.79 1.79 0 4 0 c 6.21 0 8 1.79 8 4 c f Q\n"
            ."q 0 G 1 w 7.5 4 m 7.5 5.93 5.93 7.5 4 7.5 c 2.07 7.5 0.5 5.93 0.5 4 c 0.5 2.07 2.07 0.5 4 0.5 c 5.93 0.5 7.5 2.07 7.5 4 c S Q\n"
            ."q 0.4 g 6 4 m 6 5.1 5.1 6 4 6 c 2.9 6 2 5.1 2 4 c 2 2.9 2.9 2 4 2 c 5.1 2 6 2.9 6 4 c f Q\n",
            PdfReader::data($reader->object(5)),
        );
        self::assertStringEndsWith('S Q\n', str_replace("\n", '\\n', PdfReader::data($reader->object(6))));
    }

    public function testAnAppearanceIsCompressedFromTwoHundredAndFiftySixBytes(): void
    {
        $appearance = static function (int $length, bool $compress): string {
            $pdf = new Document(compress: $compress);
            $pdf->addPage();
            $pdf->field(new TextField('n', 0, 0, 100, 20, str_repeat('x', $length)));

            return new PdfReader($pdf->render())->find('/BBox [0 0 100 20]');
        };
        $length = 257 - \strlen(PdfReader::data($appearance(1, false)));

        self::assertSame(256, \strlen(PdfReader::data($appearance($length, false))));
        self::assertStringContainsString('/Filter /FlateDecode', $appearance($length, true));
        self::assertStringNotContainsString('/Filter', $appearance($length - 1, true));
        self::assertSame(255, \strlen(PdfReader::data($appearance($length - 1, true))));
    }

    public function testANameEscapesItsDelimitersAndBytesOutsideThePrintableRange(): void
    {
        self::assertSame('/A#23#25#2F#28#29#3C#3E#5B#5D#7B#7D#20#7F#C3#A9~!', Fields::name("A#%/()<>[]{} \x7Fé~!"));
    }

    public function testAnEmbeddedFontAFieldTypesInIsEmbeddedWholeWhileTheFormIsInteractive(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600, 0x42 => 700, 0x43 => 800])->build());
        $pdf = new Document(compress: false, conformance: Conformance::PdfA2u);
        $pdf->addPage();
        $pdf->field(new TextField('n', 0, 0, 100, 20, 'A', font: $font));

        $interactive = new PdfReader($pdf->render());
        self::assertStringContainsString('/W [1 [600] 2 [700] 3 [800]]', $interactive->find('/CIDFontType2'));
        self::assertStringContainsString('/BaseFont /'.$font->postScriptName().' ', $interactive->find('/CIDFontType2'));
        self::assertStringContainsString("<0002> <0042>\n<0003> <0043>", PdfReader::data($interactive->object((int) preg_replace('/.*\/ToUnicode (\d+) 0 R.*/s', '$1', $interactive->find('/Type0')))));
        self::assertStringContainsString('(/F1 10 Tf 0 g)', $interactive->find('/FT /Tx'));
        self::assertStringContainsString('<0001> Tj', PdfReader::data($interactive->find('/BBox [0 0 100 20]')));

        $flat = new PdfReader($pdf->render(true));
        self::assertStringContainsString('/W [1 [600]]', $flat->find('/CIDFontType2'));
        self::assertMatchesRegularExpression('/\/BaseFont \/[A-P]{6}\+/', $flat->find('/CIDFontType2'));
    }

    public function testAnEmptyFieldInAnEmbeddedFontStillWritesTheFont(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build());
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new ChoiceField('c', 0, 0, 100, 20, ['A'], font: $font));

        $reader = new PdfReader($pdf->render(true));

        self::assertStringContainsString('/W []', $reader->find('/CIDFontType2'));
    }

    public function testTheColourOfATextFieldPaintsItsText(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new TextField('n', 0, 0, 100, 20, 'Ann', 10, color: Color::rgb(255, 0, 0)));

        self::assertSame("/Tx BMC\nq 1 1 98 18 re W n\nBT /F1 10 Tf 1 0 0 rg 2 6.5 Td (Ann) Tj ET\nQ\nEMC\n", PdfReader::data(new PdfReader($pdf->render())->find('/BBox [0 0 100 20]')));
    }

    public function testAComboBoxKeepsItsFrameAroundItsLabel(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new ChoiceField('c', 0, 0, 100, 20, ['m' => 'Medium'], 'm', border: Color::gray(0), background: Color::rgb(255, 255, 204)));

        self::assertSame(
            "q 1 1 0.8 rg 0 0 100 20 re f Q\nq 0 G 1 w 0.5 0.5 99 19 re S Q\n/Tx BMC\nq 1 1 98 18 re W n\nBT /F1 10 Tf 0 g 2 6.5 Td (Medium) Tj ET\nQ\nEMC\n",
            PdfReader::data(new PdfReader($pdf->render())->find('/BBox [0 0 100 20]')),
        );
    }

    public function testAListBoxTellsTheIndexOfItsSelectedValueNotOfItsLabel(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new ChoiceField('c', 0, 0, 100, 60, ['s' => 'Small', 'm' => 'Medium', 'l' => 'Large'], 'm', combo: false));

        self::assertStringContainsString('/V (m) /I [1]', new PdfReader($pdf->render())->find('/FT /Ch'));
    }

    public function testARadioGroupOfNumbersNamesItsStatesByTheirNumbers(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new RadioGroup('size', [new RadioButton('1', 0, 0, 10), new RadioButton('2', 20, 0, 10)], '2'));

        $reader = new PdfReader($pdf->render());

        self::assertStringContainsString('/AP << /N << /1 ', $reader->find('/Rect [0 0 10 10]'));
        self::assertStringContainsString('/2 ', $reader->find('/Rect [20 0 30 10]'));
    }

    public function testTheButtonsAreTwelvePointsWideByDefault(): void
    {
        self::assertSame(12.0, new CheckBox('c', 0, 0)->size);
        self::assertSame(12.0, new RadioButton('r', 0, 0)->size);
    }

    public function testATextFieldTakesAsManyCharactersAsItsValueHolds(): void
    {
        self::assertSame(1, new TextField('n', 0, 0, 10, 10, 'A', maxLength: 1)->maxLength);
        self::assertSame(1, new TextField('n', 0, 0, 10, 10, 'é', maxLength: 1)->maxLength);
        self::assertSame(2, new TextField('n', 0, 0, 10, 10, 'éa', maxLength: 2)->maxLength);
    }

    public function testAFlattenedFormIsDrawnAfterTheContentOfItsPage(): void
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->text(10, 10, 'Page', 10, StandardFont::Helvetica);
        $pdf->field(new CheckBox('c', 20, 20, 10, true));

        $contents = new PdfReader($pdf->render(true))->contents();

        self::assertSame("BT /F1 10 Tf 10 10 Td (Page) Tj ET\nq 1 0 0 1 20 20 cm /Fm1_1 Do Q\n", $contents);
    }

    public function testAnEmptyPageHasItsEmptyContentCompressed(): void
    {
        $pdf = new Document();
        $pdf->addPage();

        self::assertStringContainsString("<< /Filter /FlateDecode /Length 8 >>\nstream\n".gzcompress('', 9)."\nendstream", $pdf->render());
    }

    public function testEveryCharacterAWholeFontMapsKeepsTheCodePointTheTextUsed(): void
    {
        $font = TrueTypeFont::fromBytes(
            TrueTypeBuilder::with([0x41 => 600, 0x43 => 800])
                ->table('cmap', TrueTypeBuilder::cmapTable([[3, 10, TrueTypeBuilder::format12Subtable([[0x41, 0x41, 1], [0x42, 0x42, 1], [0x43, 0x43, 2]])]]))
                ->build(),
        );
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new TextField('n', 0, 0, 100, 20, 'A', font: $font));

        $reader = new PdfReader($pdf->render());

        self::assertStringContainsString("<0001> <0041>\n<0002> <0043>\n", PdfReader::data($reader->object((int) preg_replace('/.*\/ToUnicode (\d+) 0 R.*/s', '$1', $reader->find('/Type0')))));
    }

    public function testACharactersMapListsWhatTheFontPrintsInOrder(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x43 => 600, 0x41 => 700])->build());

        self::assertSame([0x41 => 1, 0x43 => 2], $font->characters());
    }

    public function testAConformingDocumentRefusesAFieldInAStandardFont(): void
    {
        $pdf = new Document(conformance: Conformance::PdfA2u);
        $pdf->addPage();

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('A PDF/A-2u document embeds its fonts; Helvetica cannot be used, a TrueType font can.');

        $pdf->field(new TextField('n', 0, 0, 100, 20));
    }

    public function testAConformingDocumentRefusesAChoiceInAStandardFontEvenEmpty(): void
    {
        $pdf = new Document(conformance: Conformance::PdfA2u);
        $pdf->addPage();

        $this->expectException(InvalidDocument::class);

        $pdf->field(new ChoiceField('c', 0, 0, 100, 20, ['A'], font: StandardFont::HelveticaBold));
    }

    public function testAConformingDocumentTakesButtonsWithoutAFont(): void
    {
        $pdf = new Document(compress: false, conformance: Conformance::PdfA2u);
        $pdf->addPage();
        $pdf->field(new CheckBox('c', 0, 0));

        self::assertStringContainsString('/AcroForm << /Fields [7 0 R] >>', new PdfReader($pdf->render())->object(1));
    }

    public function testAFieldNeedsAPage(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('Add a page before placing a field on it.');

        new Document()->field(new CheckBox('c', 0, 0));
    }

    public function testAFieldNameIsUniqueInTheDocument(): void
    {
        $pdf = new Document();
        $pdf->addPage();
        $pdf->field(new CheckBox('c', 0, 0));
        $pdf->addPage();

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('A field name is unique in a document; "c" is already placed.');

        $pdf->field(new TextField('c', 0, 0, 10, 10));
    }

    public function testARefusedFieldLeavesItsNameFree(): void
    {
        $pdf = new Document(conformance: Conformance::PdfA2u);
        $pdf->addPage();

        try {
            $pdf->field(new TextField('c', 0, 0, 10, 10));
            self::fail('A standard font is refused.');
        } catch (InvalidDocument) {
        }

        $pdf->field(new CheckBox('c', 0, 0));
        self::assertStringContainsString('/T (c)', $pdf->render());
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('invalidFields')]
    public function testAnInvalidFieldIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function invalidFields(): iterable
    {
        $name = 'A field name is UTF-8 text of 1 to 127 bytes without a period; ';

        yield 'an empty name' => [static fn (): CheckBox => new CheckBox('', 0, 0), $name.'"" is not.'];
        yield 'a name with a period' => [static fn (): CheckBox => new CheckBox('a.b', 0, 0), $name.'"a.b" is not.'];
        yield 'a name too long' => [static fn (): CheckBox => new CheckBox(str_repeat('a', 128), 0, 0), $name];
        yield 'a name not in UTF-8' => [static fn (): CheckBox => new CheckBox("\xFF", 0, 0), $name];
        yield 'an export value Off' => [static fn (): CheckBox => new CheckBox('c', 0, 0, exportValue: 'Off'), 'An export value is UTF-8 text of 1 to 127 bytes other than "Off"; "Off" is not.'];
        yield 'an empty export value' => [static fn (): RadioButton => new RadioButton('', 0, 0), 'An export value is UTF-8'];
        yield 'an export value too long' => [static fn (): RadioButton => new RadioButton(str_repeat('é', 64), 0, 0), 'An export value is UTF-8'];
        yield 'an export value not in UTF-8' => [static fn (): RadioButton => new RadioButton("\xC3", 0, 0), 'An export value is UTF-8'];
        yield 'a size of zero' => [static fn (): CheckBox => new CheckBox('c', 0, 0, 0), 'A field size is above zero and at most 14400 points; 0 is not.'];
        yield 'a button too large' => [static fn (): RadioButton => new RadioButton('a', 0, 0, 14_400.5), 'A field size is above zero and at most 14400 points; 14400.5 is not.'];
        yield 'a width of zero' => [static fn (): TextField => new TextField('t', 0, 0, 0, 10), 'A field width is above zero'];
        yield 'a negative height' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 10, -1, ['A']), 'A field height is above zero'];
        yield 'a font size of zero' => [static fn (): TextField => new TextField('t', 0, 0, 10, 10, fontSize: 0), 'A field font size is above zero'];
        yield 'a choice font size of zero' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 10, 10, ['A'], fontSize: 0), 'A field font size is above zero'];
        yield 'a choice width of zero' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 0, 10, ['A']), 'A field width is above zero'];
        yield 'a text height of zero' => [static fn (): TextField => new TextField('t', 0, 0, 10, 0), 'A field height is above zero'];
        yield 'a line break on one line' => [static fn (): TextField => new TextField('t', 0, 0, 10, 10, "a\nb"), 'A text field value is UTF-8 text on one line.'];
        yield 'a carriage return on one line' => [static fn (): TextField => new TextField('t', 0, 0, 10, 10, "a\rb"), 'A text field value is UTF-8 text on one line.'];
        yield 'a value not in UTF-8' => [static fn (): TextField => new TextField('t', 0, 0, 10, 10, "\xFF", multiline: true), 'A text field value is UTF-8 text.'];
        yield 'a value longer than its maximum' => [static fn (): TextField => new TextField('t', 0, 0, 10, 10, 'éèà', maxLength: 2), 'The most characters of the field "t" is at least 1 and at least the length of its value; 2 is not.'];
        yield 'a maximum of zero' => [static fn (): TextField => new TextField('t', 0, 0, 10, 10, maxLength: 0), 'The most characters of the field "t" is at least 1'];
        yield 'a radio group without buttons' => [static fn (): RadioGroup => new RadioGroup('r', []), 'The radio group "r" has at least one button.'];
        yield 'two buttons of one value' => [static fn (): RadioGroup => new RadioGroup('r', [new RadioButton('a', 0, 0), new RadioButton('a', 20, 0)]), 'Each button of the radio group "r" has its own value.'];
        yield 'a radio value it lacks' => [static fn (): RadioGroup => new RadioGroup('r', [new RadioButton('a', 0, 0), new RadioButton('b', 20, 0)], 'c'), 'The radio group "r" chooses one of its values (a, b); "c" is not.'];
        yield 'a radio group with a bad name' => [static fn (): RadioGroup => new RadioGroup('', [new RadioButton('a', 0, 0)]), $name];
        yield 'a choice without options' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 10, 10, []), 'The choice field "c" has at least one option, each with its own value.'];
        yield 'two options of one value' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 10, 10, ['A', 'A']), 'The choice field "c" has at least one option, each with its own value.'];
        yield 'an editable list box' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 10, 10, ['A'], combo: false, editable: true), 'The choice field "c" is a list box; only a combo box takes a typed value.'];
        yield 'a choice it lacks' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 10, 10, ['a' => 'A', 'b' => 'B'], 'A'), 'The choice field "c" chooses one of its values (a, b); "A" is not.'];
        yield 'a typed choice on two lines' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 10, 10, ['A'], "x\ny", editable: true), 'A typed choice is UTF-8 text on one line.'];
        yield 'a label on two lines' => [static fn (): ChoiceField => new ChoiceField('c', 0, 0, 10, 10, ["A\nB"]), 'A choice label is UTF-8 text on one line.'];
        yield 'a choice with a bad name' => [static fn (): ChoiceField => new ChoiceField('a.b', 0, 0, 10, 10, ['A']), $name];
        yield 'a text field with a bad name' => [static fn (): TextField => new TextField('a.b', 0, 0, 10, 10), $name];
    }

    public function testAFieldOfTheLargestMeasuresIsTaken(): void
    {
        $field = new TextField(str_repeat('é', 63).'a', 0, 0, 14_400, 14_400, 'ab', 14_400, maxLength: 2);
        $button = new RadioButton(str_repeat('a', 127), 0, 0, 14_400);

        self::assertSame(2, $field->maxLength);
        self::assertSame(14_400.0, $button->size);
    }

    private function sheet(): Document
    {
        $pdf = new Document(compress: false);
        $pdf->addPage();
        $pdf->field(new TextField('name', 72, 680, 200, 20, 'Ann (B)', maxLength: 20, required: true, tooltip: 'Nom', border: Color::gray(0), background: Color::rgb(255, 255, 204)));
        $pdf->field(new CheckBox('feat #1', 72, 530, 10, true));
        $pdf->field(new RadioGroup('size', [new RadioButton('S', 72, 500, 10), new RadioButton('M/L', 92, 500, 10)], 'M/L', readOnly: true));

        return $pdf;
    }
}
