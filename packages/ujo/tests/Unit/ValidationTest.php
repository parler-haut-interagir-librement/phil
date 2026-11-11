<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit;

use Phil\Ujo\Block;
use Phil\Ujo\Contract\Sized;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Bounded;
use Phil\Ujo\Schema\Context;
use Phil\Ujo\Schema\Dictionary;
use Phil\Ujo\Schema\Field;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Property;
use Phil\Ujo\Schema\Shape;
use Phil\Ujo\Type\BlockType;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Ujo;
use Phil\Ujo\Validation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validation::class)]
#[CoversClass(Bounded::class)]
#[CoversClass(Property::class)]
#[CoversClass(Shape::class)]
#[CoversClass(Context::class)]
#[CoversClass(Normalizer::class)]
#[CoversClass(Html::class)]
#[CoversClass(Plain::class)]
#[CoversClass(Link::class)]
#[CoversClass(ListOf::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Dictionary::class)]
#[CoversClass(BlockType::class)]
#[CoversClass(StandardTypes::class)]
#[CoversClass(Ujo::class)]
final class ValidationTest extends TestCase
{
    public function testPublicationCoversTheShapeAndNotTheOtherWayRound(): void
    {
        self::assertTrue(Validation::Shape->covers(Validation::Shape));
        self::assertFalse(Validation::Shape->covers(Validation::Publication));
        self::assertTrue(Validation::Publication->covers(Validation::Shape));
        self::assertTrue(Validation::Publication->covers(Validation::Publication));
        self::assertSame(['shape', 'publication'], array_column(Validation::cases(), 'value'));
    }

    public function testAContextChecksTheShapeByDefault(): void
    {
        self::assertSame(Validation::Shape, new Context(new Normalizer(), new InlineSanitizer(), 8)->level());
        self::assertSame(Validation::Publication, $this->context(Validation::Publication)->level());
    }

    /**
     * @return iterable<string, array{Sized, mixed, int, string}>
     */
    public static function sizes(): iterable
    {
        yield 'html, the text a reader sees' => [new Html(), " <b>a&amp;b</b>\u{A0}<br> ", 3, 'characters'];
        yield 'html of tags only' => [new Html(), '<br>&nbsp;', 0, 'characters'];
        yield 'html keeps the letters around a no-break space' => [new Html(), 'à', 1, 'characters'];
        yield 'plain without its white space at both ends' => [new Plain(), "\t <b> \n", 3, 'characters'];
        yield 'plain not a text' => [new Plain(), null, 0, 'characters'];
        yield 'html not a text' => [new Html(), null, 0, 'characters'];
        yield 'link' => [new Link(), '/a b', 4, 'characters'];
        yield 'link, in characters and not in bytes' => [new Link(), '/é', 2, 'characters'];
        yield 'html decodes the quotes of html 5' => [new Html(), '&quot;&apos;', 2, 'characters'];
        yield 'link not a text' => [new Link(), null, 0, 'characters'];
        yield 'list' => [new ListOf(new Integer()), [1, 2], 2, 'items'];
        yield 'list not an array' => [new ListOf(new Integer()), 'a', 0, 'items'];
        yield 'blocks' => [new Blocks(), [1], 1, 'blocks'];
        yield 'blocks not an array' => [new Blocks(), null, 0, 'blocks'];
        yield 'dictionary' => [new Dictionary(), ['a' => 'x', 'b' => 'y'], 2, 'entries'];
        yield 'dictionary not an array' => [new Dictionary(), 'a', 0, 'entries'];
        yield 'bounded, the size of its field' => [new Bounded(new ListOf(new Integer()), 5), [1], 1, 'items'];
    }

    #[DataProvider('sizes')]
    public function testASizedFieldMeasuresItsValues(Sized $field, mixed $value, int $size, string $unit): void
    {
        self::assertSame($size, $field->size($value));
        self::assertSame($unit, $field->unit());
    }

    public function testBoundsAreCheckedOnlyForPublication(): void
    {
        $field = new Bounded(new Plain(), 2, 3);

        self::assertSame('a', $field->normalize('a', 'x', $this->context(Validation::Shape)));
        self::assertSame('abcd', $field->normalize('abcd', 'x', $this->context(Validation::Shape)));
        self::assertSame('ab', $field->normalize('ab', 'x', $this->context(Validation::Publication)));
        self::assertSame('abc', $field->normalize('abc', 'x', $this->context(Validation::Publication)));
        self::assertSame('a', new Bounded(new Plain())->normalize('a', 'x', $this->context(Validation::Publication)));
    }

    /**
     * @return iterable<string, array{Bounded, mixed, string}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'too short' => [new Bounded(new Plain(), 2, 3), 'a', 'at least 2 characters are required to publish, 1 are given'];
        yield 'too long' => [new Bounded(new Plain(), 2, 3), 'abcd', 'at most 3 characters are allowed to publish, 4 are given'];
        yield 'too few items' => [new Bounded(new ListOf(new Integer()), 1), [], 'at least 1 items are required to publish, 0 are given'];
        yield 'the field first' => [new Bounded(new Plain(), 2), 3, 'a text is expected'];
    }

    #[DataProvider('outOfBounds')]
    public function testAValueOutOfBoundsCannotBePublished(Bounded $field, mixed $value, string $reason): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at x: '.$reason.'.');

        $field->normalize($value, 'x', $this->context(Validation::Publication));
    }

    public function testAPropertyIsFilledInWhenAsked(): void
    {
        $property = Property::optional(new Integer());

        self::assertFalse($property->filled);
        self::assertTrue($property->filled()->filled);
        self::assertTrue($property->filled()->nullable()->filled);
        self::assertTrue($property->nullable()->filled()->nullable);
        self::assertSame([false, true, 4], [Property::defaults(new Integer(), 4)->filled()->required, Property::defaults(new Integer(), 4)->filled()->hasDefault, Property::defaults(new Integer(), 4)->filled()->default]);
        self::assertTrue(Property::required(new Integer())->filled()->required);
    }

    /**
     * @return iterable<string, array{Field, mixed, bool}>
     */
    public static function fillings(): iterable
    {
        yield 'a sized field, of a size' => [new Html(), '<b>a</b>', true];
        yield 'a sized field, empty' => [new Html(), '<b> </b>', false];
        yield 'a sized field, absent' => [new ListOf(new Integer()), null, false];
        yield 'another field, a value' => [new Integer(), 0, true];
        yield 'another field, false' => [new Boolean(), false, true];
        yield 'another field, absent' => [new Integer(), null, false];
        yield 'another field, an empty text' => [new Shape(), '', false];
        yield 'another field, an empty array' => [new Shape(), [], false];
    }

    #[DataProvider('fillings')]
    public function testAValueFillsAPropertyIn(Field $field, mixed $value, bool $filled): void
    {
        self::assertSame($filled, Property::optional($field)->isFilledBy($value));
    }

    public function testAShapeAsksItsFilledPropertiesOnlyForPublication(): void
    {
        $shape = new Shape([
            'title' => Property::required(new Html())->filled(),
            'note' => Property::defaults(new Plain(), '')->filled(),
            'size' => Property::optional(new Integer())->filled()->nullable(),
            'other' => Property::optional(new Plain()),
        ]);

        self::assertSame(['title' => '', 'note' => ''], $shape->normalize(['title' => ''], 'x', $this->context(Validation::Shape)));
        self::assertSame(['title' => 'a', 'note' => 'b', 'size' => 0], $shape->normalize(['title' => 'a', 'note' => 'b', 'size' => 0], 'x', $this->context(Validation::Publication)));

        foreach ([
            'x.title' => ['title' => '<br>', 'note' => 'b', 'size' => 1],
            'x.note' => ['title' => 'a', 'size' => 1],
            'x.size' => ['title' => 'a', 'note' => 'b'],
            'x.size ' => ['title' => 'a', 'note' => 'b', 'size' => null],
        ] as $path => $data) {
            try {
                $shape->normalize($data, 'x', $this->context(Validation::Publication));
                self::fail('An empty value was published at '.$path.'.');
            } catch (InvalidDocument $invalidDocument) {
                self::assertSame('The UJO document is invalid at '.trim($path).': this value must be filled in to publish.', $invalidDocument->getMessage());
            }
        }
    }

    public function testADraftIsSavedAndCannotBePublished(): void
    {
        $draft = new Document([new Block('h', 'header', ['text' => '']), new Block('l', 'list', ['style' => 'ordered', 'items' => []])]);
        $ujo = new Ujo();

        self::assertCount(2, $ujo->normalize($draft)->blocks);
        self::assertCount(2, $ujo->normalize($draft, Validation::Shape)->blocks);

        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[0].data.text: this value must be filled in to publish.');

        $ujo->normalize($draft, Validation::Publication);
    }

    public function testJsonIsDecodedAtTheLevelGiven(): void
    {
        $json = '{"blocks":[{"id":"q","type":"quote","data":{"text":" "}}]}';
        $ujo = new Ujo();

        self::assertCount(1, $ujo->decode($json)->blocks);

        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[0].data.text: this value must be filled in to publish.');

        $ujo->decode($json, Validation::Publication);
    }

    /**
     * @return iterable<string, array{Block, string}>
     */
    public static function unpublishable(): iterable
    {
        yield 'an empty heading' => [new Block('b', 'header', ['text' => '<b></b>']), 'data.text: this value must be filled in to publish'];
        yield 'a list without items' => [new Block('b', 'list', ['style' => 'unordered', 'items' => []]), 'data.items: at least 1 items are required to publish, 0 are given'];
        yield 'an empty list item' => [new Block('b', 'list', ['style' => 'unordered', 'items' => [['content' => 'a', 'items' => [['content' => '']]]]]), 'data.items[0].items[0].content: this value must be filled in to publish'];
        yield 'an empty quote' => [new Block('b', 'quote', ['text' => '']), 'data.text: this value must be filled in to publish'];
        yield 'empty code' => [new Block('b', 'code', ['code' => "\n"]), 'data.code: this value must be filled in to publish'];
        yield 'a table without rows' => [new Block('b', 'table', []), 'data.content: this value must be filled in to publish'];
        yield 'a person without a name' => [new Block('b', 'personality', ['name' => ' ']), 'data.name: this value must be filled in to publish'];
    }

    #[DataProvider('unpublishable')]
    public function testTheStandardTypesSayWhatTheyNeedToBePublished(Block $block, string $reason): void
    {
        $normalizer = new Normalizer();

        self::assertCount(1, $normalizer->normalize(new Document([$block]))->blocks);

        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[0].'.$reason.'.');

        $normalizer->normalize(new Document([$block]), Validation::Publication);
    }

    public function testAListIsPublishedFromItsFirstItem(): void
    {
        // A registry of its own: the default one is built once, by whichever test comes first.
        $normalizer = new Normalizer(new BlockTypeRegistry(StandardTypes::list()));

        self::assertCount(1, $normalizer->normalize(new Document([new Block('l', 'list', ['style' => 'unordered', 'items' => [['content' => 'a']]])]), Validation::Publication)->blocks);

        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[0].data.items: at least 1 items are required to publish, 0 are given.');

        $normalizer->normalize(new Document([new Block('l', 'list', ['style' => 'unordered', 'items' => []])]), Validation::Publication);
    }

    public function testAFullDocumentIsPublished(): void
    {
        $document = new Document([
            new Block('p', 'paragraph', ['text' => '']),
            new Block('h', 'header', ['text' => 'Title']),
            new Block('l', 'list', ['style' => 'unordered', 'items' => [['content' => 'a']]]),
            new Block('t', 'table', ['content' => [['a']]]),
        ]);

        self::assertCount(4, new Normalizer()->normalize($document, Validation::Publication)->blocks);
    }

    public function testADocumentCheckedForItsShapeIsCheckedAgainForPublication(): void
    {
        $normalizer = new Normalizer();
        $draft = $normalizer->normalize(new Document([new Block('h', 'header', ['text' => 'T'])]));

        self::assertSame($draft, $normalizer->normalize($draft));
        self::assertNotSame($draft, $published = $normalizer->normalize($draft, Validation::Publication));
        self::assertSame($published, $normalizer->normalize($published, Validation::Publication));
        self::assertSame($published, $normalizer->normalize($published));

        $empty = $normalizer->normalize(new Document([new Block('h', 'header', ['text' => ''])]));

        $this->expectException(InvalidDocument::class);

        $normalizer->normalize($empty, Validation::Publication);
    }

    public function testTheHookOfATypeRunsOnlyForPublication(): void
    {
        $types = new BlockTypeRegistry(new BlockType('image', new Shape([
            'alt' => Property::defaults(new Plain(), ''),
            'caption' => Property::defaults(new Plain(), ''),
        ]), publication: static function (array $data, string $path): void {
            if ('' === $data['alt'] && '' === $data['caption']) {
                throw InvalidDocument::at($path, 'an alternative text or a caption is required to publish');
            }
        }));
        $normalizer = new Normalizer($types);
        $document = new Document([new Block('i', 'image', [])]);

        self::assertCount(1, $normalizer->normalize($document)->blocks);
        self::assertCount(1, $normalizer->normalize(new Document([new Block('i', 'image', ['alt' => 'a'])]), Validation::Publication)->blocks);

        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at blocks[0].data: an alternative text or a caption is required to publish.');

        $normalizer->normalize($document, Validation::Publication);
    }

    public function testTheLimitsHoldForADraft(): void
    {
        $this->expectExceptionMessageIsOrContains('the document holds more blocks than the 1 it may');

        new Normalizer(maxBlocks: 1)->normalize(new Document([new Block('a', 'paragraph', ['text' => '']), new Block('b', 'paragraph', ['text' => ''])]));
    }

    private function context(Validation $level): Context
    {
        return new Context(new Normalizer(), new InlineSanitizer(), 8, level: $level);
    }
}
