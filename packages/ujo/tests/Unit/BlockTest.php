<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Block::class)]
#[CoversClass(Document::class)]
#[CoversClass(InvalidDocument::class)]
final class BlockTest extends TestCase
{
    public function testABlockIsReadFromItsJsonForm(): void
    {
        $block = Block::fromArray(['id' => 'a_B-9', 'type' => 'paragraph', 'data' => ['text' => 't'], 'tunes' => ['anchor' => ['anchor' => 'x']]], 'blocks[0]');

        self::assertSame('a_B-9', $block->id);
        self::assertSame('paragraph', $block->type);
        self::assertSame(['text' => 't'], $block->data);
        self::assertSame(['anchor' => ['anchor' => 'x']], $block->tunes);
        self::assertSame(['anchor' => 'x'], $block->tune('anchor'));
        self::assertNull($block->tune('alignment'));
        self::assertSame([], Block::fromArray(['id' => 'a', 'type' => 'p', 'data' => []], 'b')->tunes);
        self::assertSame(str_repeat('i', 64), Block::fromArray(['id' => str_repeat('i', 64), 'type' => 'T'.str_repeat('y', 63), 'data' => []], 'b')->id);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidBlocks(): iterable
    {
        yield 'not an object' => ['text', 'blocks[0]: a block must be an object.'];
        yield 'another key' => [['id' => 'a', 'type' => 'p', 'data' => [], 'extra' => 1], 'blocks[0].extra: a block holds only "id", "type", "data" and "tunes".'];
        yield 'no identifier' => [['type' => 'p', 'data' => []], 'blocks[0].id: the identifier must be 1 to 64 letters, digits, "_" or "-".'];
        yield 'empty identifier' => [['id' => '', 'type' => 'p', 'data' => []], 'blocks[0].id: the identifier must be 1 to 64 letters, digits, "_" or "-".'];
        yield 'long identifier' => [['id' => str_repeat('i', 65), 'type' => 'p', 'data' => []], 'blocks[0].id: the identifier must be 1 to 64 letters, digits, "_" or "-".'];
        yield 'identifier with a space' => [['id' => 'a b', 'type' => 'p', 'data' => []], 'blocks[0].id: the identifier must be 1 to 64 letters, digits, "_" or "-".'];
        yield 'numeric identifier' => [['id' => 1, 'type' => 'p', 'data' => []], 'blocks[0].id: the identifier must be 1 to 64 letters, digits, "_" or "-".'];
        yield 'identifier ending with a new line' => [['id' => "a\n", 'type' => 'p', 'data' => []], 'blocks[0].id: the identifier must be 1 to 64 letters, digits, "_" or "-".'];
        yield 'no type' => [['id' => 'a', 'data' => []], 'blocks[0].type: the type must be a name starting with a letter.'];
        yield 'type starting with a digit' => [['id' => 'a', 'type' => '1p', 'data' => []], 'blocks[0].type: the type must be a name starting with a letter.'];
        yield 'long type' => [['id' => 'a', 'type' => 'T'.str_repeat('y', 64), 'data' => []], 'blocks[0].type: the type must be a name starting with a letter.'];
        yield 'type ending with a new line' => [['id' => 'a', 'type' => "p\n", 'data' => []], 'blocks[0].type: the type must be a name starting with a letter.'];
        yield 'no data' => [['id' => 'a', 'type' => 'p'], 'blocks[0].data: the data must be an object.'];
        yield 'data as text' => [['id' => 'a', 'type' => 'p', 'data' => 'x'], 'blocks[0].data: the data must be an object.'];
        yield 'tunes as text' => [['id' => 'a', 'type' => 'p', 'data' => [], 'tunes' => 'x'], 'blocks[0].tunes: the tunes must be an object.'];
        yield 'tunes as a list' => [['id' => 'a', 'type' => 'p', 'data' => [], 'tunes' => ['x']], 'blocks[0].tunes: the tunes must be an object.'];
    }

    #[DataProvider('invalidBlocks')]
    public function testABlockOfTheWrongStructureIsRefused(mixed $block, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at '.$message);

        Block::fromArray($block, 'blocks[0]');
    }

    public function testTheInvalidDocumentNamesItsPath(): void
    {
        self::assertSame('blocks[2].data', InvalidDocument::at('blocks[2].data', 'x')->path);
    }

    public function testABlockIsAValue(): void
    {
        $block = new Block('a', 'paragraph', ['text' => 't'], ['anchor' => ['anchor' => 'x']]);

        self::assertEquals(new Block('a', 'paragraph', ['text' => 'u'], ['anchor' => ['anchor' => 'x']]), $block->withData(['text' => 'u']));
        self::assertEquals(new Block('a', 'paragraph', ['text' => 't'], []), $block->withTunes([]));
        self::assertEquals(new Block('a', 'header', ['text' => 't'], ['anchor' => ['anchor' => 'x']]), $block->withType('header'));
        self::assertSame(['text' => 't'], $block->data);
    }

    public function testNestedBlocksAreFoundAndWrittenInTheirJsonForm(): void
    {
        $inner = new Block('i', 'paragraph', ['text' => 'in']);
        $deep = new Block('d', 'paragraph', ['text' => 'deep']);
        $toggle = new Block('t', 'toggle', ['text' => 'x', 'blocks' => [new Block('n', 'note', ['blocks' => [$deep]])]]);
        $columns = new Block('c', 'columns', ['cols' => [['blocks' => [$inner]], ['blocks' => [$toggle]]]], ['alignment' => ['alignment' => 'left']]);

        self::assertSame([$inner, $toggle], $columns->children());
        self::assertSame([
            'id' => 'c',
            'type' => 'columns',
            'data' => ['cols' => [
                ['blocks' => [['id' => 'i', 'type' => 'paragraph', 'data' => ['text' => 'in']]]],
                ['blocks' => [['id' => 't', 'type' => 'toggle', 'data' => ['text' => 'x', 'blocks' => [['id' => 'n', 'type' => 'note', 'data' => ['blocks' => [['id' => 'd', 'type' => 'paragraph', 'data' => ['text' => 'deep']]]]]]]]]],
            ]],
            'tunes' => ['alignment' => ['alignment' => 'left']],
        ], $columns->toArray());

        $document = new Document([$columns, new Block('p', 'paragraph', ['text' => 'p'])]);

        self::assertSame(['c', 'i', 't', 'n', 'd', 'p'], array_map(static fn (Block $block): string => $block->id, iterator_to_array($document->walk(), false)));
        self::assertSame($deep, $document->find('d'));
        self::assertNull($document->find('z'));
    }

    public function testADocumentIsReadFromItsJsonForm(): void
    {
        $document = Document::fromArray(['time' => 0, 'blocks' => [['id' => 'a', 'type' => 'p', 'data' => []]], 'version' => '2.31.7']);

        self::assertSame(0, $document->time);
        self::assertSame('2.31.7', $document->version);
        self::assertEquals([new Block('a', 'p')], $document->blocks);
        self::assertSame(['time' => 0, 'blocks' => [['id' => 'a', 'type' => 'p', 'data' => []]], 'version' => '2.31.7'], $document->toArray());
        self::assertSame(['blocks' => []], Document::fromArray(['blocks' => []])->toArray());
        self::assertTrue(new Document()->isEmpty());
        self::assertFalse($document->isEmpty());
        self::assertEquals(new Document([], 0, '2.31.7'), $document->withBlocks([]));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'another key' => [['blocks' => [], 'author' => 'x'], 'author: a document holds only "time", "blocks" and "version".'];
        yield 'a numeric key' => [['blocks' => [], 3 => 'x'], '3: a document holds only "time", "blocks" and "version".'];
        yield 'negative time' => [['blocks' => [], 'time' => -1], 'time: the time must be a positive integer of milliseconds.'];
        yield 'time as text' => [['blocks' => [], 'time' => '1'], 'time: the time must be a positive integer of milliseconds.'];
        yield 'version as number' => [['blocks' => [], 'version' => 2], 'version: the version must be a text.'];
        yield 'no blocks' => [[], 'blocks: the blocks must be a list.'];
        yield 'blocks as an object' => [['blocks' => ['a' => []]], 'blocks: the blocks must be a list.'];
        yield 'a wrong block' => [['blocks' => [['id' => 'a', 'type' => 'p', 'data' => []], 'x']], 'blocks[1]: a block must be an object.'];
    }

    /**
     * @param array<array-key, mixed> $document
     */
    #[DataProvider('invalidDocuments')]
    public function testADocumentOfTheWrongStructureIsRefused(array $document, string $message): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at '.$message);

        Document::fromArray($document);
    }
}
