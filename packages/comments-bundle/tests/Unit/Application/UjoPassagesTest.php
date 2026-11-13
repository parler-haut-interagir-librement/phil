<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Application;

use LogicException;
use Phil\CommentsBundle\Application\UjoPassages;
use Phil\CommentsBundle\Domain\Anchoring\Passage;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\CommentsBundle\Tests\Unit\Fixture\Json;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UjoPassages::class)]
final class UjoPassagesTest extends TestCase
{
    public function testTheTextsOfADocument(): void
    {
        $passages = array_map(static fn (Passage $passage): array => [$passage->block, $passage->field, $passage->text], UjoPassages::of($this->document()));

        self::assertSame([
            ['p', 'text', 'one two & three'],
            ['l', 'items.0.content', 'first'],
            ['l', 'items.0.items.0.content', 'inner'],
            ['t', 'content.0.0', 'cell <'],
            ['t', 'content.0.1', 'next'],
            ['i', 'caption', 'A picture'],
            ['k', 'code', 'a &lt; b'],
            ['m', 'latex', 'x &lt; y'],
            ['g', 'text', 'Open me'],
            ['n', 'text', 'nested'],
            ['q', 'text', 'Q'],
            ['q', 'caption', 'C'],
            ['w', 'title', 'W'],
            ['w', 'message', 'M'],
            ['d', 'items.0.term', 'T'],
            ['d', 'items.0.description', 'D'],
            ['s', 'name', 'Goblin'],
            ['s', 'subtitle', 'small'],
            ['deep', 'a.'.implode('.', array_fill(0, 14, 'x')).'.text', 'fifteen'],
        ], $passages);
    }

    public function testASuggestionIsAppliedWhereThePassageIs(): void
    {
        $document = $this->document();

        self::assertSame('one <b>2</b> &amp; three', $this->block(UjoPassages::apply($document, Anchor::of('p', 'text', 4, 'two'), '2'), 'p')->data['text']);
        self::assertSame('nested &lt;i&gt;!&lt;/i&gt;', $this->block(UjoPassages::apply($document, Anchor::of('n', 'text', 0, 'nested'), 'nested <i>!</i>'), 'n')->data['text']);
        self::assertSame('Open me', $this->block(UjoPassages::apply($document, Anchor::of('n', 'text', 0, 'nested'), 'x'), 'g')->data['text']);
        self::assertSame('a < b', $this->block(UjoPassages::apply($document, Anchor::of('k', 'code', 2, '&lt;'), '<'), 'k')->data['code']);
        self::assertSame('é <b>chat</b>', $this->block(UjoPassages::apply(new Document([new Block('e', 'paragraph', ['text' => 'é <b>cat</b>'])]), Anchor::of('e', 'text', 2, 'cat'), 'chat'), 'e')->data['text']);
        self::assertSame('éé > b', $this->block(UjoPassages::apply(new Document([new Block('c', 'code', ['code' => 'éé < b'])]), Anchor::of('c', 'code', 3, '<'), '>'), 'c')->data['code']);
        self::assertSame('x &lt; z', $this->block(UjoPassages::apply($document, Anchor::of('m', 'latex', 7, 'y'), 'z'), 'm')->data['latex']);

        $changed = UjoPassages::apply($document, Anchor::of('l', 'items.0.items.0.content', 0, 'inner'), 'deep');
        $list = $this->block($changed, 'l')->data;
        self::assertSame(['first', 'deep', 'ordered'], [Json::at($list, 'items.0.content'), Json::at($list, 'items.0.items.0.content'), $list['style']]);
        self::assertSame('cell &lt; x', Json::at($this->block(UjoPassages::apply($document, Anchor::of('t', 'content.0.0', 5, '<'), '< x'), 't')->data, 'content.0.0'));
        self::assertEquals($this->document()->blocks[0], $changed->blocks[0]);
        self::assertSame([7, 'v1'], [$changed->time, $changed->version]);
    }

    public function testAPassageThatIsNotThereIsRefused(): void
    {
        foreach ([
            [Anchor::of(null, null, 0, 'one'), 'The passage is not in a text of the document.'],
            [Anchor::of('zz', 'text', 0, 'one'), 'The passage is not in a text of the document.'],
            [Anchor::of('p', 'caption', 0, 'one'), 'The passage is not in a text of the document.'],
            [Anchor::of('l', 'items.0', 0, 'one'), 'The passage is not in a text of the document.'],
            [Anchor::of('l', 'items.9.content', 0, 'one'), 'The passage is not in a text of the document.'],
            [Anchor::of('p', 'text.x', 0, 'one'), 'The passage is not in a text of the document.'],
            [Anchor::of('p', 'text', 1, 'one'), 'The passage is no longer at its place in the document.'],
            [Anchor::of('k', 'code', 2, '<'), 'The passage is no longer at its place in the document.'],
        ] as [$anchor, $message]) {
            try {
                UjoPassages::apply($this->document(), $anchor, 'x');
                self::fail($message);
            } catch (InvalidComment $invalidComment) {
                self::assertSame($message, $invalidComment->getMessage());
            }
        }
    }

    private function block(Document $document, string $id): Block
    {
        return $document->find($id) ?? throw new LogicException($id);
    }

    private function document(): Document
    {
        $deep = ['text' => 'fifteen'];

        for ($i = 0; $i < 14; ++$i) {
            $deep = ['x' => $deep];
        }

        return new Document([
            new Block('p', 'paragraph', ['text' => 'one <b>two</b> &amp; three', 'bad-key' => 'skipped', 'url' => 'not words']),
            new Block('l', 'list', ['style' => 'ordered', 'items' => [['content' => 'first', 'meta' => ['checked' => true], 'items' => [['content' => 'inner', 'items' => []]]]]]),
            new Block('t', 'table', ['withHeadings' => false, 'content' => [['cell &lt;', 'next']]]),
            new Block('i', 'image', ['file' => ['url' => 'https://example.com/a.png'], 'caption' => 'A <i>picture</i>']),
            new Block('k', 'code', ['code' => 'a &lt; b', 'language' => 'php']),
            new Block('m', 'math', ['latex' => 'x &lt; y']),
            new Block('g', 'toggle', ['text' => 'Open me', 'blocks' => [new Block('n', 'paragraph', ['text' => 'nested'])]]),
            new Block('q', 'quote', ['text' => 'Q', 'caption' => 'C', 'alignment' => 'left']),
            new Block('w', 'warning', ['bad-key' => 'skipped', 'title' => 'W', 'message' => 'M']),
            new Block('d', 'definitionList', ['items' => [['term' => 'T', 'description' => 'D']]]),
            new Block('s', 'statblock', ['name' => 'Goblin', 'subtitle' => 'small', 'ac' => 15, 'tags' => ['a', 'b']]),
            new Block('deep', 'container', ['a' => $deep, 'b' => ['x' => $deep]]),
        ], 7, 'v1');
    }
}
