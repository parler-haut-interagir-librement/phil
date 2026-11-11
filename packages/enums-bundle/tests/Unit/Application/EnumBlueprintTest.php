<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\EnumsBundle\Application\Maker\EnumBlueprint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(EnumBlueprint::class)]
final class EnumBlueprintTest extends TestCase
{
    public function testAStringEnumIsValuedByItsNamesInSnakeCase(): void
    {
        $blueprint = EnumBlueprint::of(EnumBlueprint::STRING, ['Pending', 'InTransit', 'ON_HOLD', 'refundedTwice']);

        self::assertSame('string', $blueprint->backing);
        self::assertFalse($blueprint->translatable);
        self::assertSame([
            ['name' => 'Pending', 'value' => 'pending', 'label' => 'Pending'],
            ['name' => 'InTransit', 'value' => 'in_transit', 'label' => 'In transit'],
            ['name' => 'ON_HOLD', 'value' => 'on_hold', 'label' => 'On hold'],
            ['name' => 'refundedTwice', 'value' => 'refunded_twice', 'label' => 'Refunded twice'],
        ], $blueprint->cases);
    }

    public function testAnIntegerEnumCountsFromOne(): void
    {
        self::assertSame([1, 2], array_column(EnumBlueprint::of(EnumBlueprint::INT, ['Low', 'High'])->cases, 'value'));
    }

    public function testAPureEnumHasNoValue(): void
    {
        $blueprint = EnumBlueprint::of(EnumBlueprint::PURE, ['Circle', 'Square_2'], true);

        self::assertSame([null, null], array_column($blueprint->cases, 'value'));
        self::assertSame(['Circle', 'Square 2'], array_column($blueprint->cases, 'label'));
        self::assertTrue($blueprint->translatable);
    }

    public function testUnderscoresAtTheEdgesOrDoubledMakeNoEmptyWord(): void
    {
        self::assertSame([
            ['name' => '_Private', 'value' => 'private', 'label' => 'Private'],
            ['name' => 'ON__HOLD_', 'value' => 'on_hold', 'label' => 'On hold'],
        ], EnumBlueprint::of(EnumBlueprint::STRING, ['_Private', 'ON__HOLD_'])->cases);
    }

    public function testANameOutsideAsciiIsAnIdentifier(): void
    {
        self::assertSame('éclair', EnumBlueprint::of(EnumBlueprint::STRING, ['Éclair'])->cases[0]['value']);
    }

    /**
     * The values of an integer or pure enum do not come from the names, so names of the same words are fine there.
     */
    public function testNamesOfTheSameWordsAreFineWithoutStringValues(): void
    {
        self::assertCount(2, EnumBlueprint::of(EnumBlueprint::INT, ['FooBar', 'Foo_Bar'])->cases);
        self::assertCount(2, EnumBlueprint::of(EnumBlueprint::PURE, ['FooBar', 'Foo_Bar'])->cases);
    }

    /**
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function refused(): iterable
    {
        yield 'an unknown backing' => ['float', ['One'], 'An enum is backed by "string" or "int", or is "pure"; "float" given.'];
        yield 'no case' => [EnumBlueprint::STRING, [], 'An enum needs at least one case.'];
        yield 'a name with a space' => [EnumBlueprint::STRING, ['In transit'], '"In transit" is not a PHP identifier.'];
        yield 'a name starting with a digit' => [EnumBlueprint::STRING, ['2nd'], '"2nd" is not a PHP identifier.'];
        yield 'a name with a dash' => [EnumBlueprint::STRING, ['on-hold'], '"on-hold" is not a PHP identifier.'];
        yield 'a name twice' => [EnumBlueprint::STRING, ['One', 'Two', 'One', 'Two'], 'The case One, Two is named twice.'];
        yield 'a name ending in a new line' => [EnumBlueprint::STRING, ["One\n"], '"One'];
        yield 'two names of the same digits' => [EnumBlueprint::STRING, ['_1', '__1'], 'Several cases would have the same value: "1" for _1, __1;'];
        yield 'two names of the same words' => [EnumBlueprint::STRING, ['FooBar', 'Foo_Bar', 'Other'], 'Several cases would have the same value: "foo_bar" for FooBar, Foo_Bar;'];
        yield 'two names in another case' => [EnumBlueprint::STRING, ['ACTIVE', 'Active', 'Inactive', 'INACTIVE'], '"active" for ACTIVE, Active; "inactive" for Inactive, INACTIVE;'];
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('refused')]
    public function testWhatIsNotAnEnumIsRefused(string $backing, array $names, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        EnumBlueprint::of($backing, $names);
    }

    public function testTheSourceOfAStringEnum(): void
    {
        self::assertSame(<<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Enum;

            use Phil\Enums\Attribute\Label;
            use Phil\Enums\Attribute\Meta;
            use Phil\Enums\Traits\AsEnumerableEnumTrait;

            #[Meta(Label::class)]
            enum OrderStatus: string
            {
                use AsEnumerableEnumTrait;

                #[Label('Pending')]
                case Pending = 'pending';

                #[Label('In transit')]
                case InTransit = 'in_transit';
            }

            PHP, EnumBlueprint::of(EnumBlueprint::STRING, ['Pending', 'InTransit'])->source('App\Enum', 'OrderStatus'));
    }

    public function testTheSourceOfATranslatablePureEnumAndOfAnIntegerEnum(): void
    {
        $pure = EnumBlueprint::of(EnumBlueprint::PURE, ['Circle'], true)->source('App\Enum', 'Shape');

        self::assertStringContainsString("use Phil\\EnumsBundle\\Infrastructure\\Translation\\AsTranslatableEnumTrait;\nuse Symfony\\Contracts\\Translation\\TranslatableInterface;\n", $pure);
        self::assertStringContainsString("enum Shape implements TranslatableInterface\n{\n    use AsEnumerableEnumTrait;\n    use AsTranslatableEnumTrait;\n", $pure);
        self::assertStringContainsString("    #[Label('Circle')]\n    case Circle;\n", $pure);

        $int = EnumBlueprint::of(EnumBlueprint::INT, ['Low', 'High'])->source('App\Enum', 'Level');
        self::assertStringContainsString("enum Level: int\n{", $int);
        self::assertStringContainsString("    case High = 2;\n}\n", $int);
        self::assertStringNotContainsString('Translat', $int);
    }

    /**
     * A label holding a quote or a backslash is written as a valid single-quoted literal.
     */
    public function testTheSourceEscapesItsStringLiterals(): void
    {
        $source = EnumBlueprint::of(EnumBlueprint::STRING, ['Ok'])->source('App\Enum', 'Answer');
        $quoted = new ReflectionMethod(EnumBlueprint::class, 'literal')->invoke(EnumBlueprint::of(EnumBlueprint::STRING, ['Ok']), "it's a \\ path");

        self::assertStringContainsString("case Ok = 'ok';", $source);
        self::assertSame("'it\\'s a \\\\ path'", $quoted);
    }
}
