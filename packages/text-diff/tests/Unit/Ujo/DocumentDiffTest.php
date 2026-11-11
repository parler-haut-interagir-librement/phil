<?php

declare(strict_types=1);

namespace Phil\TextDiff\Tests\Unit\Ujo;

use Phil\TextDiff\Diff;
use Phil\TextDiff\Tests\Unit\Operations;
use Phil\TextDiff\TextDiff;
use Phil\TextDiff\Tokenizer;
use Phil\TextDiff\Ujo\BlockChange;
use Phil\TextDiff\Ujo\DocumentDiff;
use Phil\TextDiff\Ujo\DocumentDiffer;
use Phil\TextDiff\Ujo\FieldChange;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentDiffer::class)]
#[CoversClass(DocumentDiff::class)]
#[CoversClass(BlockChange::class)]
#[CoversClass(FieldChange::class)]
#[CoversClass(TextDiff::class)]
final class DocumentDiffTest extends TestCase
{
    public function testIdenticalDocumentsKeepEveryBlock(): void
    {
        $document = new Document([$this->paragraph('a', 'One'), $this->paragraph('b', 'Two')]);
        $diff = new TextDiff()->documents($document, $document);

        self::assertTrue($diff->isIdentical());
        self::assertSame([], $diff->changes());
        self::assertSame([['unchanged', 'a', null, 0, 0], ['unchanged', 'b', null, 1, 1]], $this->summary($diff->blocks));
        self::assertSame($document->blocks[1], $diff->blocks[1]->old);
        self::assertSame($document->blocks[1], $diff->blocks[1]->new);
        self::assertSame('paragraph', $diff->blocks[1]->type);
        self::assertSame([], $diff->blocks[1]->fields);
    }

    public function testEmptyDocuments(): void
    {
        $diff = new TextDiff()->documents(new Document(), new Document());

        self::assertSame([], $diff->blocks);
        self::assertTrue($diff->isIdentical());
    }

    public function testAChangedTextIsComparedWordByWord(): void
    {
        $diff = new TextDiff()->documents(
            new Document([$this->paragraph('a', 'Hello <b>world</b>'), $this->paragraph('b', 'Same')]),
            new Document([$this->paragraph('a', 'Hello <b>there</b>'), $this->paragraph('b', 'Same')]),
        );

        self::assertFalse($diff->isIdentical());
        self::assertSame([['changed', 'a', null, 0, 0], ['unchanged', 'b', null, 1, 1]], $this->summary($diff->blocks));
        self::assertSame([['changed', 'a', null, 0, 0]], $this->summary($diff->changes()));
        $field = $diff->blocks[0]->fields[0];
        self::assertCount(1, $diff->blocks[0]->fields);
        self::assertSame(['data.text', 'Hello <b>world</b>', 'Hello <b>there</b>'], [$field->path, $field->old, $field->new]);
        self::assertInstanceOf(Diff::class, $field->diff);
        self::assertSame(Tokenizer::Html, $field->diff->tokenizer);
        self::assertSame(['=Hello| |<b>', '-world', '+there', '=</b>'], Operations::describe($field->diff->operations));
    }

    public function testAddedRemovedAndMovedBlocks(): void
    {
        $diff = new TextDiff()->documents(
            new Document([$this->paragraph('a', 'Hello world'), $this->header('b', 'Title'), $this->paragraph('c', 'Gone')]),
            new Document([$this->header('b', 'Title'), $this->paragraph('a', 'Hello there world'), $this->paragraph('d', 'New')]),
        );

        self::assertSame([['unchanged', 'b', null, 1, 0], ['removed', 'c', null, 2, null], ['moved', 'a', null, 0, 1], ['added', 'd', null, null, 2]], $this->summary($diff->blocks));
        [, $removed, $moved, $added] = $diff->blocks;
        self::assertSame(['paragraph', null, []], [$removed->type, $removed->new, $removed->fields]);
        self::assertSame('Gone', $removed->old?->data['text']);
        self::assertSame([$removed->old], $removed->before());
        self::assertSame([], $removed->after());
        self::assertSame(['paragraph', null, []], [$added->type, $added->old, $added->fields]);
        self::assertSame('New', $added->new?->data['text']);
        self::assertSame([], $added->before());
        self::assertSame([$added->new], $added->after());
        self::assertSame(['data.text'], array_map(static fn (FieldChange $field): string => $field->path, $moved->fields));
        self::assertSame('Hello world', $moved->old?->data['text']);
        self::assertSame('Hello there world', $moved->new?->data['text']);
    }

    public function testABlockMovedDownwardsIsListedWhereItNowStands(): void
    {
        $diff = new TextDiff()->documents(
            new Document([$this->paragraph('a', 'A'), $this->paragraph('b', 'B'), $this->paragraph('c', 'C')]),
            new Document([$this->paragraph('b', 'B'), $this->paragraph('c', 'C'), $this->paragraph('a', 'A')]),
        );

        self::assertSame([['unchanged', 'b', null, 1, 0], ['unchanged', 'c', null, 2, 1], ['moved', 'a', null, 0, 2]], $this->summary($diff->blocks));
        self::assertSame([], $diff->blocks[2]->fields);
        self::assertFalse($diff->isIdentical());
    }

    public function testTypeAndTunesAreCompared(): void
    {
        $diff = new TextDiff()->documents(
            new Document([new Block('a', 'paragraph', ['text' => 'T']), new Block('b', 'paragraph', ['text' => 'U'], ['alignment' => ['alignment' => 'left']])]),
            new Document([new Block('a', 'header', ['text' => 'T', 'level' => 2]), new Block('b', 'paragraph', ['text' => 'U'], ['alignment' => ['alignment' => 'center'], 'anchor' => ['anchor' => 'x']])]),
        );

        self::assertSame([['changed', 'a', null, 0, 0], ['changed', 'b', null, 1, 1]], $this->summary($diff->blocks));
        self::assertSame('header', $diff->blocks[0]->type);
        self::assertSame([['type', 'paragraph', 'header', true], ['data.level', null, 2, false]], $this->fields($diff->blocks[0]));
        self::assertSame([['tunes.alignment.alignment', 'left', 'center', true], ['tunes.anchor', null, ['anchor' => 'x'], false]], $this->fields($diff->blocks[1]));
    }

    public function testValuesOfOtherTypesHaveNoTextDifference(): void
    {
        $diff = new TextDiff()->documents(
            new Document([new Block('a', 'list', ['style' => 'ordered', 'items' => ['one', 'two', 'three'], 'start' => 1, 'flag' => true])]),
            new Document([new Block('a', 'list', ['style' => 'ordered', 'items' => ['one', 'deux'], 'start' => '1', 'flag' => false])]),
        );

        self::assertSame([['data.items.1', 'two', 'deux', true], ['data.items.2', 'three', null, false], ['data.start', 1, '1', false], ['data.flag', true, false, false]], $this->fields($diff->blocks[0]));
    }

    public function testNestedBlocksAreComparedLevelByLevel(): void
    {
        $old = new Document([new Block('cols', 'columns', ['cols' => [
            ['blocks' => [$this->paragraph('x', 'Left'), $this->paragraph('y', 'Kept')]],
            ['blocks' => [$this->paragraph('z', 'Right')]],
        ]])]);
        $new = new Document([new Block('cols', 'columns', ['cols' => [
            ['blocks' => [$this->paragraph('x', 'Left side'), $this->paragraph('y', 'Kept')]],
            ['blocks' => []],
        ]])]);

        $diff = new TextDiff()->documents($old, $new);

        self::assertSame([['changed', 'cols', null, 0, 0], ['changed', 'x', 'cols', 0, 0], ['unchanged', 'y', 'cols', 1, 1], ['removed', 'z', 'cols', 0, null]], $this->summary($diff->blocks));
        self::assertSame([], $diff->blocks[0]->fields);
        self::assertSame([['data.text', 'Left', 'Left side', true]], $this->fields($diff->blocks[1]));
        self::assertSame([['changed', 'cols', null, 0, 0]], $this->summary($diff->topLevel()));
        self::assertSame([['changed', 'cols', null, 0, 0], ['changed', 'x', 'cols', 0, 0], ['removed', 'z', 'cols', 0, null]], $this->summary($diff->changes()));
    }

    public function testABlockAddedAtTheStartShiftsTheOthers(): void
    {
        $diff = new TextDiff()->documents(
            new Document([self::paragraph('a', 'A'), self::paragraph('b', 'B')]),
            new Document([self::paragraph('n', 'N'), self::paragraph('a', 'A'), self::paragraph('b', 'B')]),
        );

        self::assertSame([['added', 'n', null, null, 0], ['unchanged', 'a', null, 0, 1], ['unchanged', 'b', null, 1, 2]], self::summary($diff->blocks));
    }

    public function testTheTopLevelIsAList(): void
    {
        $diff = new TextDiff()->documents(
            new Document([new Block('t', 'toggle', ['text' => 'More', 'blocks' => [self::paragraph('p', 'In')]]), self::paragraph('a', 'A')]),
            new Document([new Block('t', 'toggle', ['text' => 'More', 'blocks' => [self::paragraph('p', 'In')]]), self::paragraph('a', 'A')]),
        );

        self::assertSame([['unchanged', 't', null, 0, 0], ['unchanged', 'a', null, 1, 1]], self::summary($diff->topLevel()));
    }

    public function testAKeptContainerWithKeptChildrenIsUnchanged(): void
    {
        $document = new Document([new Block('t', 'toggle', ['text' => 'More', 'blocks' => [$this->paragraph('p', 'Inside'), $this->paragraph('q', 'Also')]])]);
        $diff = new TextDiff()->documents($document, $document);

        self::assertSame([['unchanged', 't', null, 0, 0], ['unchanged', 'p', 't', 0, 0], ['unchanged', 'q', 't', 1, 1]], $this->summary($diff->blocks));
    }

    public function testAContainerWithOneChangedChildOfSeveralIsChanged(): void
    {
        $diff = new TextDiff()->documents(
            new Document([new Block('t', 'toggle', ['text' => 'More', 'blocks' => [$this->paragraph('p', 'Inside'), $this->paragraph('q', 'Also')]])]),
            new Document([new Block('t', 'toggle', ['text' => 'More', 'blocks' => [$this->paragraph('p', 'Inside'), $this->paragraph('q', 'Too')]])]),
        );

        self::assertSame([['changed', 't', null, 0, 0], ['unchanged', 'p', 't', 0, 0], ['changed', 'q', 't', 1, 1]], $this->summary($diff->blocks));
        self::assertSame([], $diff->blocks[0]->fields);
    }

    public function testABlockHeldAloneIsALevelOfOne(): void
    {
        $diff = new TextDiff()->documents(
            new Document([new Block('w', 'wrapper', ['content' => $this->paragraph('p', 'Old')])]),
            new Document([new Block('w', 'wrapper', ['content' => $this->paragraph('q', 'New')])]),
        );

        self::assertSame([['changed', 'w', null, 0, 0], ['removed', 'p', 'w', 0, null], ['added', 'q', 'w', null, 0]], $this->summary($diff->blocks));
    }

    public function testBlocksReplacedByOtherValuesAreExported(): void
    {
        $diff = new TextDiff()->documents(
            new Document([new Block('w', 'wrapper', ['content' => $this->paragraph('p', 'Old'), 'items' => [$this->paragraph('r', 'R'), 'text']])]),
            new Document([new Block('w', 'wrapper', ['content' => 'plain', 'items' => [$this->paragraph('r', 'R'), 'text']])]),
        );

        self::assertSame([['data.content', ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'Old']], 'plain', false]], $this->fields($diff->blocks[0]));
        self::assertSame([['changed', 'w', null, 0, 0], ['unchanged', 'r', 'w', 0, 0]], $this->summary($diff->blocks));
    }

    public function testListsOfBlocksAndValuesInsideArraysAreExported(): void
    {
        $diff = new TextDiff()->documents(
            new Document([new Block('w', 'wrapper', ['items' => [[$this->paragraph('r', 'R')], 'x']])]),
            new Document([new Block('w', 'wrapper', ['items' => 'none'])]),
        );

        self::assertSame([['data.items', [[['id' => 'r', 'type' => 'paragraph', 'data' => ['text' => 'R']]], 'x'], 'none', false]], $this->fields($diff->blocks[0]));
    }

    public function testEveryBlockIsListedWhenTheBoundIsPassed(): void
    {
        $diff = new TextDiff(0)->documents(
            new Document([$this->paragraph('a', 'A'), $this->paragraph('b', 'B')]),
            new Document([$this->paragraph('b', 'B'), $this->paragraph('c', 'C')]),
        );

        self::assertSame([['removed', 'a', null, 0, null], ['moved', 'b', null, 1, 0], ['added', 'c', null, null, 1]], $this->summary($diff->blocks));
    }

    private function paragraph(string $id, string $text): Block
    {
        return new Block($id, 'paragraph', ['text' => $text]);
    }

    private function header(string $id, string $text): Block
    {
        return new Block($id, 'header', ['text' => $text, 'level' => 2]);
    }

    /**
     * @param list<BlockChange> $blocks
     *
     * @return list<array{string, string, ?string, ?int, ?int}>
     */
    private function summary(array $blocks): array
    {
        return array_map(static fn (BlockChange $block): array => [$block->kind->value, $block->id, $block->parent, $block->oldIndex, $block->newIndex], $blocks);
    }

    /**
     * @return list<array{string, mixed, mixed, bool}>
     */
    private function fields(BlockChange $block): array
    {
        return array_map(static fn (FieldChange $field): array => [$field->path, $field->old, $field->new, $field->diff instanceof Diff], $block->fields);
    }
}
