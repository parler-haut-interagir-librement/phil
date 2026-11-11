<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Json;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\InvalidJson;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Json\JsonEncoder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonDecoder::class)]
#[CoversClass(JsonEncoder::class)]
#[CoversClass(InvalidJson::class)]
final class JsonTest extends TestCase
{
    public function testUjoJsonIsReadIntoADocument(): void
    {
        $document = new JsonDecoder()->decode('{"time":1762387200000,"blocks":[{"id":"a","type":"paragraph","data":{"text":"Héllo","n":{"m":[1,{"o":2}]}},"tunes":{"t":{}}}],"version":"2.31.7"}');

        self::assertEquals(new Document([new Block('a', 'paragraph', ['text' => 'Héllo', 'n' => ['m' => [1, ['o' => 2]]]], ['t' => []])], 1_762_387_200_000, '2.31.7'), $document);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'syntax' => ['{"blocks":', 'The UJO JSON cannot be parsed: Syntax error'];
        yield 'list' => ['[]', 'The UJO JSON must be an object with a "blocks" list.'];
        yield 'text' => ['"blocks"', 'The UJO JSON must be an object with a "blocks" list.'];
        yield 'too deep' => ['{"blocks":[[[1]]]}', 'The UJO JSON cannot be parsed: Maximum stack depth exceeded'];
        yield 'too large' => [str_pad('{"blocks":[]}', 65, ' '), 'The UJO JSON is 65 bytes long, above the limit of 64 bytes.'];
    }

    #[DataProvider('invalidJson')]
    public function testATextThatIsNotUjoJsonIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidJson::class);
        $this->expectExceptionMessageIsOrContains($message);

        new JsonDecoder(64, 3)->decode($json);
    }

    public function testTheLimitsAreInclusive(): void
    {
        self::assertSame([], new JsonDecoder(64, 3)->decode(str_pad('{"blocks":[]}', 64, ' '))->blocks);
        self::assertSame([], new JsonDecoder(14, 3)->decode('{"blocks":[ ]}')->blocks);
        self::assertSame(4_194_304, JsonDecoder::DEFAULT_MAX_BYTES);
        self::assertSame(64, JsonDecoder::DEFAULT_MAX_DEPTH);
    }

    public function testTheStructureIsCheckedAfterTheSyntax(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks: the blocks must be a list.');

        new JsonDecoder()->decode('{"blocks":{"a":1}}');
    }

    public function testBigNumbersAreReadAsTexts(): void
    {
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at time: the time must be a positive integer of milliseconds.');

        new JsonDecoder()->decode('{"time":123456789012345678901234567890,"blocks":[]}');
    }

    public function testADocumentIsWrittenAsEditorJsReadsIt(): void
    {
        $document = new Document([
            new Block('a', 'list', ['style' => 'unordered', 'meta' => [], 'items' => [['content' => 'é/<b>', 'meta' => [], 'items' => []]], 'ratio' => 1.0]),
            new Block('b', 'delimiter', []),
            new Block('c', 'image', ['file' => [], 'settings' => [], 'other' => []], ['alignment' => ['alignment' => 'left']]),
        ], 5, '2.31.7');

        self::assertSame('{"time":5,"blocks":[{"id":"a","type":"list","data":{"style":"unordered","meta":{},"items":[{"content":"é/<b>","meta":{},"items":[]}],"ratio":1.0}},{"id":"b","type":"delimiter","data":{}},{"id":"c","type":"image","data":{"file":{},"settings":{},"other":[]},"tunes":{"alignment":{"alignment":"left"}}}],"version":"2.31.7"}', new JsonEncoder()->encode($document));
        self::assertSame("{\n    \"blocks\": []\n}", new JsonEncoder()->encode(new Document(), true));
        self::assertSame(['data', 'tunes', 'meta', 'file', 'settings'], JsonEncoder::OBJECT_KEYS);
    }

    public function testATextThatIsNotUtf8CannotBeWritten(): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks: it cannot be written as JSON: malformed UTF-8 characters, possibly incorrectly encoded.');

        new JsonEncoder()->encode(new Document([new Block('a', 'paragraph', ['text' => "\xB1"])]));
    }
}
