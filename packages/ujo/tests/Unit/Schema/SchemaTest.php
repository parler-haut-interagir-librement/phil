<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit\Schema;

use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Html\InlineSanitizer;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Schema\Blocks;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Context;
use Phil\Ujo\Schema\Field;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Lazy;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Property;
use Phil\Ujo\Schema\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shape::class)]
#[CoversClass(Property::class)]
#[CoversClass(Html::class)]
#[CoversClass(Plain::class)]
#[CoversClass(Integer::class)]
#[CoversClass(Boolean::class)]
#[CoversClass(Choice::class)]
#[CoversClass(Link::class)]
#[CoversClass(ListOf::class)]
#[CoversClass(Blocks::class)]
#[CoversClass(Lazy::class)]
final class SchemaTest extends TestCase
{
    /**
     * @return iterable<string, array{Field, mixed, mixed}>
     */
    public static function validValues(): iterable
    {
        yield 'html' => [new Html(), '<b onclick="x">a</b>', '<b>a</b>'];
        yield 'html of a profile' => [new Html('plain'), '<b>a</b>', 'a'];
        yield 'plain' => [new Plain(), '<b>a</b>', '<b>a</b>'];
        yield 'plain of a length' => [new Plain(3), 'ééé', 'ééé'];
        yield 'plain of a form' => [new Plain(null, '/^a+$/'), 'aa', 'aa'];
        yield 'integer' => [new Integer(), -5, -5];
        yield 'integer within bounds' => [new Integer(-1, 1), 1, 1];
        yield 'boolean' => [new Boolean(), false, false];
        yield 'choice' => [new Choice(['a', 'b']), 'b', 'b'];
        yield 'link' => [new Link(), '/a', '/a'];
        yield 'link of a scheme' => [new Link(['mailto'], false), 'mailto:a@example.com', 'mailto:a@example.com'];
        yield 'list' => [new ListOf(new Integer()), [1, 2], [1, 2]];
        yield 'empty list' => [new ListOf(new Integer()), [], []];
        yield 'lazy' => [new Lazy(static fn (): Field => new Integer(0)), 3, 3];
        yield 'empty shape' => [new Shape(), [], []];
        yield 'open shape' => [new Shape(['a' => Property::required(new Integer())], true), ['z' => 1, 'a' => 2], ['a' => 2, 'z' => 1]];
        yield 'nullable' => [new Shape(['a' => Property::optional(new Integer())->nullable()]), ['a' => null], ['a' => null]];
        yield 'required nullable' => [new Shape(['a' => Property::required(new Integer())->nullable()]), ['a' => null], ['a' => null]];
        yield 'defaults nullable' => [new Shape(['a' => Property::defaults(new Integer(), 4)->nullable()]), [], ['a' => 4]];
        yield 'blocks' => [new Blocks(), [], []];
    }

    #[DataProvider('validValues')]
    public function testAValueIsReturnedInCanonicalForm(Field $field, mixed $value, mixed $expected): void
    {
        self::assertSame($expected, $field->normalize($value, 'x', $this->context()));
    }

    /**
     * @return iterable<string, array{Field, mixed, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'html' => [new Html(), 1, 'a text is expected'];
        yield 'plain' => [new Plain(), null, 'a text is expected'];
        yield 'plain too long' => [new Plain(2), 'ééé', 'the text is longer than 2 characters'];
        yield 'plain of another form' => [new Plain(null, '/^a+$/'), 'ab', '"ab" is not of the expected form'];
        yield 'integer' => [new Integer(), 1.0, 'a whole number is expected'];
        yield 'integer below' => [new Integer(0), -1, '-1 is below the minimum 0'];
        yield 'integer above' => [new Integer(null, 0), 1, '1 is above the maximum 0'];
        yield 'boolean' => [new Boolean(), 0, 'true or false is expected'];
        yield 'choice' => [new Choice(['a', 'b']), 'c', 'one of "a", "b" is expected'];
        yield 'choice not a text' => [new Choice(['1']), 1, 'one of "1" is expected'];
        yield 'link' => [new Link(), 3, 'an address in http, https, or relative, is expected'];
        yield 'list' => [new ListOf(new Integer()), ['a' => 1], 'a list is expected'];
        yield 'list not an array' => [new ListOf(new Integer()), 1, 'a list is expected'];
        yield 'list too short' => [new ListOf(new Integer(), 2), [1], '1 items, where 2 to any number are expected'];
        yield 'list too long' => [new ListOf(new Integer(), 0, 1), [1, 2], '2 items, where 0 to 1 are expected'];
        yield 'shape' => [new Shape(), 'a', 'an object is expected'];
        yield 'shape as a list' => [new Shape(), [1], 'an object is expected'];
        yield 'not nullable' => [new Shape(['a' => Property::optional(new Integer())]), ['a' => null], 'a whole number is expected'];
        yield 'blocks' => [new Blocks(), 'a', 'a list of blocks is expected'];
    }

    #[DataProvider('invalidValues')]
    public function testAValueThatBreaksItsFieldIsRefused(Field $field, mixed $value, string $reason): void
    {
        $this->expectException(InvalidDocument::class);
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at x'.(str_starts_with($reason, 'a whole number') && $field instanceof Shape ? '.a' : '').': '.$reason.'.');

        $field->normalize($value, 'x', $this->context());
    }

    public function testAnItemOfAListNamesItsIndex(): void
    {
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at x[1]: a whole number is expected.');

        new ListOf(new Integer())->normalize([1, 'b'], 'x', $this->context());
    }

    public function testPropertiesSayWhetherTheyArePresent(): void
    {
        $required = Property::required(new Integer());
        $optional = Property::optional(new Integer());
        $defaults = Property::defaults(new Integer(), 3);

        self::assertSame([true, false, null, false], [$required->required, $required->hasDefault, $required->default, $required->nullable]);
        self::assertSame([false, false, null, false], [$optional->required, $optional->hasDefault, $optional->default, $optional->nullable]);
        self::assertSame([false, true, 3, false], [$defaults->required, $defaults->hasDefault, $defaults->default, $defaults->nullable]);
        self::assertSame([false, true, 3, true], [$defaults->nullable()->required, $defaults->nullable()->hasDefault, $defaults->nullable()->default, $defaults->nullable()->nullable]);
        self::assertSame($defaults->field, $defaults->nullable()->field);
        self::assertTrue($required->nullable()->required);
    }

    public function testShapesKeepTheirProperties(): void
    {
        $shape = new Shape(['a' => Property::required(new Integer())], true);

        self::assertTrue($shape->open);
        self::assertFalse(new Shape()->open);
        self::assertSame(['a'], array_keys($shape->properties));
    }

    private function context(): Context
    {
        return new Context(new Normalizer(), new InlineSanitizer(), 8);
    }

    public function testAListWithoutMinimumMentionsZero(): void
    {
        $this->expectExceptionMessageIsOrContains('The UJO document is invalid at x: 2 items, where 0 to 1 are expected.');

        new ListOf(new Integer(), null, 1)->normalize([1, 2], 'x', $this->context());
    }
}
