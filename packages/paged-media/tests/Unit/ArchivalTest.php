<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Bytes;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\Objects;
use Phil\PagedMedia\Pdf\SrgbProfile;
use Phil\PagedMedia\Tests\Unit\Fixture\PdfReader;
use Phil\PagedMedia\Tests\Unit\Fixture\PngBuilder;
use Phil\PagedMedia\Tests\Unit\Fixture\TrueTypeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Document::class)]
#[CoversClass(Objects::class)]
#[CoversClass(Metadata::class)]
#[CoversClass(SrgbProfile::class)]
#[CoversClass(Conformance::class)]
final class ArchivalTest extends TestCase
{
    public function testAPdfADocumentCarriesItsMetadataOutputIntentAndIdentifier(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build());
        $pdf = new Document(metadata: new Metadata('Reçu', 'A & B'), compress: false, conformance: Conformance::PdfA2u);
        $pdf->addPage();
        $pdf->text(10, 10, 'A', 10, $font);
        $pdf->image(Image::fromBytes(PngBuilder::build(1, 1, 6, 8, ["\x10\x20\x30\x80"])), 0, 0, 1, 1);

        $reader = new PdfReader($pdf->render());

        self::assertSame(1, preg_match('/\A<< \/Type \/Catalog \/Pages 2 0 R \/Metadata (\d+) 0 R \/OutputIntents \[<< \/Type \/OutputIntent \/S \/GTS_PDFA1 \/OutputConditionIdentifier \(sRGB IEC61966-2\.1\) \/Info \(sRGB IEC61966-2\.1 \(phil\/paged-media\)\) \/DestOutputProfile (\d+) 0 R >>\] >>\z/', $reader->object(1), $catalog));
        $metadata = $reader->object((int) $catalog[1]);
        self::assertStringStartsWith('<< /Type /Metadata /Subtype /XML /Length ', $metadata);
        self::assertStringNotContainsString('/Filter', $metadata, 'The metadata stream is never compressed.');
        self::assertSame(new Metadata('Reçu', 'A & B')->xmp(Conformance::PdfA2u), PdfReader::data($metadata));
        $profile = $reader->object((int) $catalog[2]);
        self::assertStringStartsWith('<< /N 3 /Length ', $profile);
        self::assertSame(SrgbProfile::bytes(), PdfReader::data($profile));

        $body = substr($reader->pdf, 0, (int) strpos($reader->pdf, "xref\n"));
        $hash = strtoupper(hash('xxh128', $body));
        self::assertStringContainsString(\sprintf(' /ID [<%1$s> <%1$s>] >>', $hash), $reader->pdf);
    }

    public function testTheOutputIntentProfileIsCompressedWithTheDocument(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build());
        $pdf = new Document(conformance: Conformance::PdfA2u);
        $pdf->addPage();
        $pdf->text(10, 10, 'A', 10, $font);

        self::assertStringStartsWith('<< /N 3 /Filter /FlateDecode /Length ', new PdfReader($pdf->render())->find('/N 3 '));
    }

    public function testADocumentWithoutConformanceHasNeitherMetadataNorIdentifier(): void
    {
        $pdf = new Document();
        $pdf->addPage();

        $output = $pdf->render();

        self::assertStringNotContainsString('/ID [', $output);
        self::assertStringNotContainsString('/OutputIntents', $output);
        self::assertStringNotContainsString('/Metadata', $output);
    }

    public function testAPdfADocumentRefusesTheStandardFonts(): void
    {
        $pdf = new Document(conformance: Conformance::PdfA2u);
        $pdf->addPage();

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('A PDF/A-2u document embeds its fonts; Helvetica cannot be used, a TrueType font can.');

        $pdf->text(0, 0, 'x', 10, StandardFont::Helvetica);
    }

    public function testAPdfADocumentRefusesACharacterTheFontLacks(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build());
        $pdf = new Document(conformance: Conformance::PdfA2u);
        $pdf->addPage();
        $pdf->text(0, 0, 'A', 10, $font);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The font "TestSans-Regular" has no glyph for "€"; a PDF/A-2u document cannot print it.');

        $pdf->text(0, 0, 'A€', 10, $font);
    }

    public function testAPdfADocumentRefusesACmykImage(): void
    {
        $pdf = new Document(conformance: Conformance::PdfA2u);
        $pdf->addPage();
        $pdf->image(Image::fromBytes(ImageTest::jpeg(3)), 0, 0, 1, 1);

        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('A PDF/A-2u document defines its colours in sRGB; a CMYK image cannot be placed.');

        $pdf->image(Image::fromBytes(ImageTest::jpeg(4)), 0, 0, 1, 1);
    }

    public function testADocumentWithoutConformanceTakesWhatPdfARefuses(): void
    {
        $font = TrueTypeFont::fromBytes(TrueTypeBuilder::with([0x41 => 600])->build());
        $pdf = new Document();
        $pdf->addPage();
        $pdf->text(0, 0, 'A€', 10, $font);
        $pdf->text(0, 0, 'x');
        $pdf->image(Image::fromBytes(ImageTest::jpeg(4)), 0, 0, 1, 1);

        self::assertSame(1, $pdf->pageCount());
    }

    public function testTheXmpPacketAgreesWithTheInformationDictionary(): void
    {
        $metadata = new Metadata('Reçu <R-1>', 'A & "B"', 'Un reçu', 'reçu, pdf', 'Caisse', new DateTimeImmutable('2026-11-05 14:30:15', new DateTimeZone('America/Toronto')));

        self::assertSame(
            "<?xpacket begin=\"\u{FEFF}\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n"
            ."<x:xmpmeta xmlns:x=\"adobe:ns:meta/\">\n"
            ." <rdf:RDF xmlns:rdf=\"http://www.w3.org/1999/02/22-rdf-syntax-ns#\">\n"
            ."  <rdf:Description rdf:about=\"\" xmlns:pdfaid=\"http://www.aiim.org/pdfa/ns/id/\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" xmlns:pdf=\"http://ns.adobe.com/pdf/1.3/\" xmlns:xmp=\"http://ns.adobe.com/xap/1.0/\">\n"
            ."   <pdfaid:part>2</pdfaid:part>\n"
            ."   <pdfaid:conformance>U</pdfaid:conformance>\n"
            ."   <dc:title><rdf:Alt><rdf:li xml:lang=\"x-default\">Reçu &lt;R-1&gt;</rdf:li></rdf:Alt></dc:title>\n"
            ."   <dc:creator><rdf:Seq><rdf:li>A &amp; &quot;B&quot;</rdf:li></rdf:Seq></dc:creator>\n"
            ."   <dc:description><rdf:Alt><rdf:li xml:lang=\"x-default\">Un reçu</rdf:li></rdf:Alt></dc:description>\n"
            ."   <pdf:Keywords>reçu, pdf</pdf:Keywords>\n"
            ."   <xmp:CreatorTool>Caisse</xmp:CreatorTool>\n"
            ."   <xmp:CreateDate>2026-11-05T14:30:15-05:00</xmp:CreateDate>\n"
            ."   <pdf:Producer>phil/paged-media</pdf:Producer>\n"
            ."  </rdf:Description>\n"
            ." </rdf:RDF>\n"
            ."</x:xmpmeta>\n"
            .'<?xpacket end="w"?>',
            $metadata->xmp(Conformance::PdfA2u),
        );
    }

    public function testTheXmpPacketLeavesOutWhatIsNotGiven(): void
    {
        $xmp = new Metadata('', null, '', '')->xmp(Conformance::PdfA2u);

        foreach (['dc:title', 'dc:creator', 'dc:description', 'pdf:Keywords', 'xmp:CreatorTool', 'xmp:CreateDate'] as $property) {
            self::assertStringNotContainsString($property, $xmp);
        }

        self::assertStringContainsString("   <pdf:Producer>phil/paged-media</pdf:Producer>\n  </rdf:Description>", $xmp);
    }

    public function testTheSrgbProfileIsAVersionTwoDisplayProfile(): void
    {
        $profile = SrgbProfile::bytes();

        self::assertSame(\strlen($profile), Bytes::readUint32($profile, 0));
        self::assertSame("\x02\x10\0\0", substr($profile, 8, 4));
        self::assertSame('mntrRGB XYZ ', substr($profile, 12, 12));
        self::assertSame([2026, 1, 1, 0, 0, 0], array_map(static fn (int $at): int => Bytes::readUint16($profile, $at), [24, 26, 28, 30, 32, 34]));
        self::assertSame('acsp', substr($profile, 36, 4));
        self::assertSame([0x0000F6D6, 0x00010000, 0x0000D32D], [Bytes::readUint32($profile, 68), Bytes::readUint32($profile, 72), Bytes::readUint32($profile, 76)], 'The PCS illuminant is D50.');
        self::assertSame(str_repeat("\0", 48), substr($profile, 80, 48));

        $tags = [];

        for ($index = 0, $count = Bytes::readUint32($profile, 128); $index < $count; ++$index) {
            $entry = 132 + 12 * $index;
            $tags[substr($profile, $entry, 4)] = substr($profile, Bytes::readUint32($profile, $entry + 4), Bytes::readUint32($profile, $entry + 8));
            self::assertSame(0, Bytes::readUint32($profile, $entry + 4) % 4, 'Each tag starts on a four-byte boundary.');
        }

        self::assertSame(['desc', 'cprt', 'wtpt', 'rXYZ', 'gXYZ', 'bXYZ', 'rTRC', 'gTRC', 'bTRC'], array_keys($tags));
        self::assertSame('desc'."\0\0\0\0".Bytes::uint32(37).SrgbProfile::DESCRIPTION."\0".str_repeat("\0", 78), $tags['desc']);
        self::assertSame("text\0\0\0\0No copyright, use freely\0", $tags['cprt']);
        self::assertSame('XYZ '."\0\0\0\0".Bytes::uint32(0x6F9E).Bytes::uint32(0x38F6).Bytes::uint32(0x038F), $tags['rXYZ']);
        self::assertSame('XYZ '."\0\0\0\0".Bytes::uint32(0x6296).Bytes::uint32(0xB787).Bytes::uint32(0x18DC), $tags['gXYZ']);
        self::assertSame('XYZ '."\0\0\0\0".Bytes::uint32(0x24A2).Bytes::uint32(0x0F83).Bytes::uint32(0xB6CF), $tags['bXYZ']);
        self::assertSame('XYZ '."\0\0\0\0".Bytes::uint32(0xF6D6).Bytes::uint32(0x10000).Bytes::uint32(0xD32D), $tags['wtpt']);

        self::assertSame(2_544, \strlen($profile), 'Header, table, and each tag padded to four bytes, the shared curve once.');
        self::assertSame(Bytes::readUint32($profile, 132 + 12 * 6 + 4), Bytes::readUint32($profile, 132 + 12 * 8 + 4), 'The blue curve points at the red one.');
        self::assertSame(str_repeat("\0", 28), substr($profile, 40, 28), 'No platform, flags or device, and the perceptual intent.');
        $curve = $tags['rTRC'];
        self::assertSame([$curve, $curve], [$tags['gTRC'], $tags['bTRC']]);
        self::assertSame("curv\0\0\0\0", substr($curve, 0, 8));
        self::assertSame(1024, Bytes::readUint32($curve, 8));
        self::assertSame(12 + 2 * 1024, \strlen($curve));
        self::assertSame([0, 50, 65535], [Bytes::readUint16($curve, 12), Bytes::readUint16($curve, 12 + 2 * 10), Bytes::readUint16($curve, 12 + 2 * 1023)], 'The curve is linear near black and reaches white.');
        self::assertSame(14_057, Bytes::readUint16($curve, 12 + 2 * 512), 'The middle of the curve follows the sRGB power law.');
        self::assertSame('baaa155cf8c0a4b1e7097650d39888ec', hash('xxh128', $curve), 'Every point is rounded to the nearest.');
    }
}
