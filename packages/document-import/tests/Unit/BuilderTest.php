<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit;

use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\MediaFolder;
use Phil\DocumentImport\MediaStore;
use Phil\DocumentImport\StyleRole;
use Phil\DocumentImport\Tests\Unit\Fixture\Png;
use Phil\SvgSanitizer\SvgSanitizer;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Builder::class)]
#[CoversClass(Role::class)]
final class BuilderTest extends TestCase
{
    public function testHeadingsStayBetweenTheFirstAndTheSixthLevel(): void
    {
        self::assertSame([Role::Heading1, Role::Heading1, Role::Heading6, Role::Heading6], [Role::heading(0), Role::heading(1), Role::heading(6), Role::heading(7)]);
        self::assertSame([Role::Heading1, Role::Heading6, null], [Role::outline(0), Role::outline(5), Role::outline(9)]);
    }

    public function testEveryRoleOfTheStyleMapIsKnown(): void
    {
        self::assertSame(
            [Role::Paragraph, Role::Heading1, Role::Heading2, Role::Heading3, Role::Heading4, Role::Heading5, Role::Heading6, Role::Quote, Role::Code, null],
            array_map(Role::mapped(...), StyleRole::cases()),
        );
    }

    public function testCodeLinesJoinTheCodeBlockJustBeforeThem(): void
    {
        $out = $this->builder();
        $out->block(Role::Code, 'a &amp; b<br>c');
        $out->block(Role::Code, 'd');

        self::assertSame([['code', ['code' => "a & b\nc\nd"]]], $this->blocks($out));
    }

    public function testACodeLineEndsTheListBeforeIt(): void
    {
        $out = $this->builder();
        $out->listItem('k', 0, 'A', false);
        $out->block(Role::Code, 'x');
        $out->listItem('k', 0, 'B', false);

        self::assertSame(['list', 'code', 'list'], array_column($this->blocks($out), 0));
    }

    public function testAParagraphIsAPublicBlock(): void
    {
        $out = $this->builder();
        $out->paragraph('left', 'left');
        $out->paragraph('centered', 'center');

        self::assertSame([null, 'center'], array_map(static function (Block $block): ?string {
            $tune = $block->tune('alignment');

            return \is_array($tune) && \is_string($tune['alignment'] ?? null) ? $tune['alignment'] : null;
        }, $out->document()->blocks));
    }

    public function testAnImageIsWrittenWhereItIsMetUnlessItWaitsForItsBlock(): void
    {
        $out = $this->builder();
        $out->image(new Media('a.png', Png::bytes(), 'Dot'));
        $out->linkedImage('media/b.png', 'Linked');
        $out->paragraph('p');
        $out->image(new Media('c.png', Png::bytes()), true);
        $out->linkedImage('media/d.png', '', true);
        $out->paragraph('q');

        self::assertSame(['image', 'image', 'paragraph', 'paragraph', 'image', 'image'], array_column($this->blocks($out), 0));
    }

    public function testImagesStillWaitingAtTheEndAreWritten(): void
    {
        $out = $this->builder();
        $out->paragraph('p');
        $out->linkedImage('media/a.png', 'A', true);

        self::assertSame(['paragraph', 'image'], array_column($this->blocks($out), 0));
    }

    public function testImagesMetBeforeAListWaitForTheListAndTheOthersForTheNextBlock(): void
    {
        $out = $this->builder();
        $out->linkedImage('media/x.png', 'X', true);
        $out->listItem('k', 0, 'A', false);
        $out->linkedImage('media/y.png', 'Y', true);
        $out->linkedImage('media/z.png', 'Z', true);
        $out->paragraph('p');

        self::assertSame(['list', 'image', 'paragraph', 'image', 'image'], array_column($this->blocks($out), 0));
        self::assertSame([null, 'X', null, 'Y', 'Z'], array_map(static fn (array $block): ?string => \is_string($block[1]['alt'] ?? null) ? $block[1]['alt'] : null, $this->blocks($out)));
    }

    public function testTheTextAlternativeIsCutToAThousandCharacters(): void
    {
        $out = $this->builder();
        $out->linkedImage('media/a.png', str_repeat('é', 1001));

        $alt = $this->blocks($out)[0][1]['alt'];

        self::assertIsString($alt);
        self::assertSame(1000, mb_strlen($alt));
    }

    public function testTheFirstImageOfAnAddressKeepsItsMedium(): void
    {
        $out = $this->builder();
        $out->store(new Media('x.png', Png::bytes(), 'first'));
        $out->store(new Media('x.png', Png::bytes(), 'second'));

        self::assertSame(['media/x.png'], array_keys($out->media));
        self::assertSame('first', $out->media['media/x.png']->description);
    }

    public function testAThousandCopiesAreTriedBeforeGivingUpOnAName(): void
    {
        $store = new class implements MediaStore {
            public int $calls = 0;

            public function store(Media $media): string
            {
                ++$this->calls;

                return 'one.png';
            }
        };
        $out = new Builder($store, new SvgSanitizer());
        $out->store(new Media('a.png', Png::bytes()));

        self::assertNull($out->store(new Media('b.png', Png::bytes().'x')));
        self::assertSame(1001, $store->calls);
    }

    public function testListsTablesAndNotesAreWrittenAsTheyAreMet(): void
    {
        $out = $this->builder();
        $out->listItem('a', 0, 'One', true, 'upper-roman', 4);
        $out->listItem('b', 0, 'Two', false);
        $out->listItem('b', 1, 'Nested', false);
        $out->listItem('a', 1, 'Joins', true);
        $out->listItem('c', 0, 'Starts a list', true);
        $out->table([['a'], ['b', 'c']], true);

        $reference = $out->note('First', false).$out->note('Second', false).$out->note('End', true);
        $out->paragraph($reference);
        $document = $out->document();
        $ids = array_map(static fn (Block $block): string => $block->id, $document->blocks);
        $blocks = array_map(static fn (Block $block): array => [$block->type, $block->data], $document->blocks);

        self::assertSame([
            ['list', ['style' => 'ordered', 'meta' => ['start' => 4, 'counterType' => 'upper-roman'], 'items' => [['content' => 'One', 'meta' => [], 'items' => []]]]],
            ['list', ['style' => 'unordered', 'meta' => [], 'items' => [['content' => 'Two', 'meta' => [], 'items' => [['content' => 'Nested', 'meta' => [], 'items' => []], ['content' => 'Joins', 'meta' => [], 'items' => []]]]]]],
            ['list', ['style' => 'ordered', 'meta' => [], 'items' => [['content' => 'Starts a list', 'meta' => [], 'items' => []]]]],
            ['table', ['withHeadings' => true, 'content' => [['a', ''], ['b', 'c']]]],
            ['paragraph', ['text' => '<sup data-footnote="fn-1"><a href="#footnote-fn-1">1</a></sup><sup data-footnote="fn-2"><a href="#footnote-fn-2">2</a></sup><sup data-footnote="en-1"><a href="#footnote-en-1">1</a></sup>']],
            ['footnote', ['id' => 'fn-1', 'text' => 'First']],
            ['footnote', ['id' => 'fn-2', 'text' => 'Second']],
            ['footnote', ['id' => 'en-1', 'text' => 'End']],
        ], $blocks);
        self::assertSame(['b1', 'b2', 'b3', 'b4', 'b5', 'b6', 'b7', 'b8'], $ids);
    }

    private function builder(): Builder
    {
        return new Builder(new MediaFolder(), new SvgSanitizer());
    }

    /**
     * @return list<array{string, array<array-key, mixed>}>
     */
    private function blocks(Builder $out): array
    {
        return array_map(static function (Block $block): array {
            $array = $block->toArray();

            return [$array['type'], $array['data']];
        }, $out->document()->blocks);
    }
}
