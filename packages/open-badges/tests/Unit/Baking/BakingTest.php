<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Baking;

use Phil\OpenBadges\Baking\Baked;
use Phil\OpenBadges\Baking\PngBaker;
use Phil\OpenBadges\Baking\SvgBaker;
use Phil\OpenBadges\Exception\InvalidImage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PngBaker::class)]
#[CoversClass(SvgBaker::class)]
#[CoversClass(Baked::class)]
final class BakingTest extends TestCase
{
    /**
     * A PNG of one transparent pixel.
     */
    public const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIW2NgUPr/HwADaAIhG61j/AAAAABJRU5ErkJggg==';

    private const string JWT = 'eyJhbGciOiJFZERTQSJ9.eyJpZCI6InVybjp4In0.c2ln';

    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"><rect width="1" height="1"/></svg>';

    public function testAPngIsBakedBeforeItsEnd(): void
    {
        $png = $this->png();
        $baked = PngBaker::bake($png, self::JWT);
        $chunk = 'iTXt'."openbadgecredential\0\0\0\0\0".self::JWT;

        self::assertSame(substr($png, 0, -12).pack('N', \strlen($chunk) - 4).$chunk.pack('N', crc32($chunk)).substr($png, -12), $baked);
        self::assertSame(self::JWT, PngBaker::extract($baked));
        self::assertNull(PngBaker::extract($png));
    }

    public function testBakingAgainReplacesTheCredential(): void
    {
        $document = ['id' => 'urn:x', 'name' => 'Médaille / or', 'proof' => ['type' => 'DataIntegrityProof']];
        $baked = PngBaker::bake(PngBaker::bake($this->png(), self::JWT), $document);

        self::assertSame('{"id":"urn:x","name":"Médaille / or","proof":{"type":"DataIntegrityProof"}}', PngBaker::extract($baked));
        self::assertSame(1, substr_count($baked, 'openbadgecredential'));
        self::assertSame(\strlen(PngBaker::bake($this->png(), $document)), \strlen($baked));
    }

    public function testOtherTextChunksAreKept(): void
    {
        $png = $this->png();
        $other = $this->chunk('iTXt', "openbadges\0\0\0\0\0x").$this->chunk('tEXt', "Comment\0hi");
        $withText = substr($png, 0, 33).$other.substr($png, 33);

        $baked = PngBaker::bake($withText, self::JWT);
        self::assertStringContainsString($other, $baked);
        self::assertSame(self::JWT, PngBaker::extract($baked));
        self::assertNull(PngBaker::extract($withText));
    }

    public function testTrailingBytesAreDropped(): void
    {
        self::assertSame(PngBaker::bake($this->png(), self::JWT), PngBaker::bake($this->png().'garbage', self::JWT));
        self::assertNull(PngBaker::extract($this->png().'garbage'));
    }

    /**
     * @return iterable<string, array{callable(string): string, string}>
     */
    public static function malformedPngs(): iterable
    {
        yield 'not a PNG' => [static fn (string $png): string => 'GIF89a'.$png, 'The image is not a PNG of at most 16777216 bytes.'];
        yield 'too large' => [static fn (string $png): string => $png.str_repeat("\0", PngBaker::MAX_BYTES), 'The image is not a PNG of at most 16777216 bytes.'];
        yield 'cut short' => [static fn (string $png): string => substr($png, 0, -12), 'The PNG image ends before its IEND chunk.'];
        yield 'a bad CRC' => [static fn (string $png): string => substr($png, 0, 29).'xxxx'.substr($png, 33), 'The PNG image has a malformed chunk at byte 8.'];
        yield 'a bad type' => [static fn (string $png): string => substr($png, 0, 12).'IH1R'.substr($png, 16), 'The PNG image has a malformed chunk at byte 8.'];
        yield 'a length past the end' => [static fn (string $png): string => substr($png, 0, 8)."\0\0\xff\xff".substr($png, 12), 'The PNG image has a malformed chunk at byte 8.'];
        yield 'a missing CRC' => [static fn (string $png): string => substr($png, 0, -4).'AB', 'The PNG image has a malformed chunk at byte 58.'];
        yield 'no header first' => [static fn (string $png): string => substr($png, 0, 8).substr($png, 33), 'A PNG image starts with its IHDR chunk.'];
    }

    /**
     * @param callable(string): string $alter
     */
    #[DataProvider('malformedPngs')]
    public function testAMalformedPngIsRefused(callable $alter, string $reason): void
    {
        $this->expectExceptionObject(InvalidImage::because($reason));
        PngBaker::extract($alter($this->png()));
    }

    public function testAPngHoldsOneCredential(): void
    {
        $chunk = $this->chunk('iTXt', "openbadgecredential\0\0\0\0\0".self::JWT);
        $png = $this->png();

        $this->expectExceptionObject(InvalidImage::because('The image holds more than one baked credential.'));
        PngBaker::extract(substr($png, 0, 33).$chunk.$chunk.substr($png, 33));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedTexts(): iterable
    {
        yield 'compressed' => ["openbadgecredential\0\1\0\0\0x", 'A baked credential is not compressed.'];
        yield 'a compression method' => ["openbadgecredential\0\0\1\0\0x", 'A baked credential is not compressed.'];
        yield 'no translated keyword' => ["openbadgecredential\0\0\0en\0".self::JWT, 'The text chunk of the baked credential is malformed.'];
        yield 'not UTF-8' => ["openbadgecredential\0\0\0\0\0\xff", 'The text chunk of the baked credential is malformed.'];
        yield 'not a credential' => ["openbadgecredential\0\0\0\0\0hello", 'A baked credential is a compact JSON Web Token or a document with its proof.'];
    }

    #[DataProvider('malformedTexts')]
    public function testAMalformedTextIsRefused(string $data, string $reason): void
    {
        $png = $this->png();

        $this->expectExceptionObject(InvalidImage::because($reason));
        PngBaker::extract(substr($png, 0, 33).$this->chunk('iTXt', $data).substr($png, 33));
    }

    public function testATextWithALanguageIsRead(): void
    {
        $png = $this->png();

        self::assertSame(self::JWT, PngBaker::extract(substr($png, 0, 33).$this->chunk('iTXt', "openbadgecredential\0\0\0en\0badge\0".self::JWT).substr($png, 33)));
    }

    public function testWhatIsBakedIsACredential(): void
    {
        self::assertSame(self::JWT, Baked::text(self::JWT));
        self::assertSame('a.b.', Baked::check('a.b.'));
        self::assertTrue(Baked::isJwt('a-_.b.c'));
        self::assertFalse(Baked::isJwt("a.b.c\n"));
        self::assertFalse(Baked::isJwt('a.b'));
        self::assertFalse(Baked::isJwt('a b.c.d'));

        foreach (['{"proof":"x"}', '[1]', '{"id":"x"}', 'a.b'] as $text) {
            try {
                Baked::check($text);
                self::fail(\sprintf('"%s" was expected to be refused.', $text));
            } catch (InvalidImage $refused) {
                self::assertSame('A baked credential is a compact JSON Web Token or a document with its proof.', $refused->getMessage());
            }
        }

        self::assertSame('{"proof":{}}', Baked::check('{"proof":{}}'));
        self::assertSame('{"proof":[]}', Baked::text(['proof' => []]));
        $long = 'a.'.str_repeat('b', Baked::MAX_LENGTH - 3).'.';
        self::assertSame($long, Baked::check($long));

        $this->expectExceptionObject(InvalidImage::because('A baked credential is at most 1048576 bytes.'));
        Baked::check($long.'c');
    }

    public function testADeepDocumentIsNotACredential(): void
    {
        $deepest = '{"proof":{},"x":'.str_repeat('[', 62).str_repeat(']', 62).'}';
        self::assertSame($deepest, Baked::check($deepest));

        $this->expectException(InvalidImage::class);
        Baked::check('{"proof":{},"x":'.str_repeat('[', 63).str_repeat(']', 63).'}');
    }

    public function testAPngOfTheLargestSizeIsRead(): void
    {
        $png = $this->png();

        self::assertNull(PngBaker::extract($png.str_repeat("\0", PngBaker::MAX_BYTES - \strlen($png))));
    }

    public function testALargeChunkIsRead(): void
    {
        $png = $this->png();
        $large = $this->chunk('tEXt', "Comment\0".str_repeat('x', 70_000));
        $baked = PngBaker::bake(substr($png, 0, 33).$large.substr($png, 33), self::JWT);

        self::assertStringContainsString($large, $baked);
        self::assertSame(self::JWT, PngBaker::extract($baked));
    }

    public function testAChunkOfSixteenMebibytesIsRefused(): void
    {
        $png = $this->png();
        $chunk = $this->chunk('tEXt', 'x');

        $this->expectExceptionObject(InvalidImage::because('The PNG image has a malformed chunk at byte 33.'));
        PngBaker::extract(substr($png, 0, 33)."\x01".substr($chunk, 1).substr($png, 33));
    }

    public function testAChunkTypeIsMadeOfLetters(): void
    {
        $png = $this->png();

        $this->expectExceptionObject(InvalidImage::because('The PNG image has a malformed chunk at byte 33.'));
        PngBaker::extract(substr($png, 0, 33).$this->chunk('tEX1', 'x').substr($png, 33));
    }

    public function testAnotherKeywordIsNotACredential(): void
    {
        $png = $this->png();

        self::assertNull(PngBaker::extract(substr($png, 0, 33).$this->chunk('iTXt', "openbadgecredentials\0\0\0\0\0".self::JWT).substr($png, 33)));
    }

    public function testATextHoldingANulIsRefused(): void
    {
        $png = $this->png();

        $this->expectExceptionObject(InvalidImage::because('A baked credential is a compact JSON Web Token or a document with its proof.'));
        PngBaker::extract(substr($png, 0, 33).$this->chunk('iTXt', "openbadgecredential\0\0\0\0\0".self::JWT."\0x").substr($png, 33));
    }

    public function testAnSvgOfTheLargestSizeIsRead(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg">';
        $svg .= str_repeat(' ', SvgBaker::MAX_BYTES - \strlen($svg) - 6).'</svg>';

        self::assertSame(SvgBaker::MAX_BYTES, \strlen($svg));
        self::assertNull(SvgBaker::extract($svg));
    }

    public function testAnSvgIsBakedFirst(): void
    {
        $baked = SvgBaker::bake(self::SVG, self::JWT);

        self::assertSame("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"1\" height=\"1\"><openbadges:credential xmlns:openbadges=\"https://purl.imsglobal.org/ob/v3p0\" verify=\"".self::JWT.'"/><rect width="1" height="1"/></svg>', $baked);
        self::assertSame(self::JWT, SvgBaker::extract($baked));
        self::assertNull(SvgBaker::extract(self::SVG));
    }

    public function testAnSvgHoldsADocumentAsCharacterData(): void
    {
        $document = ['id' => 'urn:x', 'name' => 'a ]]> b', 'proof' => ['type' => 'DataIntegrityProof']];
        $baked = SvgBaker::bake(SvgBaker::bake(self::SVG, self::JWT), $document);

        self::assertStringContainsString('<openbadges:credential xmlns:openbadges="https://purl.imsglobal.org/ob/v3p0"><![CDATA[{"id":"urn:x","name":"a ]]]]><![CDATA[> b","proof":{"type":"DataIntegrityProof"}}]]></openbadges:credential><rect', $baked);
        self::assertSame('{"id":"urn:x","name":"a ]]> b","proof":{"type":"DataIntegrityProof"}}', SvgBaker::extract($baked));
        self::assertSame(1, substr_count($baked, 'openbadges:credential xmlns'));
    }

    public function testAnSvgCredentialIsTrimmed(): void
    {
        self::assertSame(self::JWT, SvgBaker::extract('<svg xmlns="http://www.w3.org/2000/svg" xmlns:ob="https://purl.imsglobal.org/ob/v3p0"><g><ob:credential>  '.self::JWT."\n</ob:credential></g></svg>"));
        self::assertSame(self::JWT, SvgBaker::extract('<svg xmlns="http://www.w3.org/2000/svg" xmlns:ob="https://purl.imsglobal.org/ob/v3p0"><ob:credential verify="'.self::JWT.'">ignored</ob:credential></svg>'));
    }

    public function testADoctypeWithoutEntitiesIsRead(): void
    {
        self::assertNull(SvgBaker::extract('<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'.self::SVG));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedSvgs(): iterable
    {
        yield 'empty' => ['', 'The image is not well-formed XML.'];
        yield 'blank' => ['  ', 'The image is not well-formed XML.'];
        yield 'not XML' => ['<svg', 'The image is not well-formed XML.'];
        yield 'a NUL' => ["<\0\0\0\x0e ueo</s20", 'The image is not well-formed XML.'];
        yield 'not UTF-8' => ["<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?><svg xmlns=\"http://www.w3.org/2000/svg\">\xe9</svg>", 'The image is not well-formed XML.'];
        yield 'too large' => ['<svg xmlns="http://www.w3.org/2000/svg">'.str_repeat(' ', SvgBaker::MAX_BYTES).'</svg>', 'An SVG image is at most 4194304 bytes.'];
        yield 'entities' => ['<!DOCTYPE svg [<!ENTITY a "x">]><svg xmlns="http://www.w3.org/2000/svg">&a;</svg>', 'An SVG image declares no entities.'];
        yield 'entities in lowercase' => ['<!DOCTYPE svg [<!entity a "x">]><svg xmlns="http://www.w3.org/2000/svg"/>', 'An SVG image declares no entities.'];
        yield 'an internal subset' => ['<!DOCTYPE svg [<!ELEMENT svg ANY>]><svg xmlns="http://www.w3.org/2000/svg"/>', 'An SVG image declares no entities.'];
        yield 'another root' => ['<html xmlns="http://www.w3.org/2000/svg"/>', "The image's root is an svg element of the SVG namespace."];
        yield 'another namespace' => ['<svg xmlns="http://www.w3.org/1999/xhtml"/>', "The image's root is an svg element of the SVG namespace."];
        yield 'two credentials' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:ob="https://purl.imsglobal.org/ob/v3p0"><ob:credential verify="a.b.c"/><ob:credential verify="a.b.c"/></svg>', 'The image holds more than one baked credential.'];
        yield 'not a credential' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:ob="https://purl.imsglobal.org/ob/v3p0"><ob:credential>hello</ob:credential></svg>', 'A baked credential is a compact JSON Web Token or a document with its proof.'];
    }

    #[DataProvider('malformedSvgs')]
    public function testAMalformedSvgIsRefused(string $svg, string $reason): void
    {
        $this->expectExceptionObject(InvalidImage::because($reason));
        SvgBaker::extract($svg);
    }

    public function testBakingRefusesAMalformedSvg(): void
    {
        $this->expectExceptionObject(InvalidImage::because('The image is not well-formed XML.'));
        SvgBaker::bake('<svg', self::JWT);
    }

    public function testBakingRefusesWhatIsNotACredential(): void
    {
        $this->expectExceptionObject(InvalidImage::because('A baked credential is a compact JSON Web Token or a document with its proof.'));
        SvgBaker::bake(self::SVG, 'hello');
    }

    private function png(): string
    {
        return base64_decode(self::PNG, true);
    }

    private function chunk(string $type, string $data): string
    {
        return pack('N', \strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
