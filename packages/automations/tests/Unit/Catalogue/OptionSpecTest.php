<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Catalogue;

use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Definition\Options;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(OptionSpec::class)]
#[CoversClass(ComponentDescription::class)]
final class OptionSpecTest extends TestCase
{
    #[DataProvider('values')]
    public function testItChecksTheTypeOfAValue(OptionType $type, mixed $value, bool $valid): void
    {
        $spec = new OptionSpec('o', $type, choices: ['a', 'b']);

        self::assertSame($valid ? null : \sprintf('The option "o" is not a valid %s.', $type->value), $spec->problem(Options::of(['o' => $value])));
    }

    /**
     * @return iterable<string, array{OptionType, mixed, bool}>
     */
    public static function values(): iterable
    {
        yield 'text' => [OptionType::Text, 'x', true];
        yield 'text as integer' => [OptionType::Text, 1, false];
        yield 'integer' => [OptionType::Integer, 1, true];
        yield 'integer as text' => [OptionType::Integer, '1', false];
        yield 'boolean' => [OptionType::Boolean, false, true];
        yield 'boolean as integer' => [OptionType::Boolean, 0, false];
        yield 'identifier' => [OptionType::Identifier, 'list:news/2024_a-b.c', true];
        yield 'identifier with a space' => [OptionType::Identifier, 'a b', false];
        yield 'identifier starting with a dash' => [OptionType::Identifier, '-a', false];
        yield 'identifier too long' => [OptionType::Identifier, str_repeat('a', 256), false];
        yield 'identifier of 255' => [OptionType::Identifier, str_repeat('a', 255), true];
        yield 'identifier as integer' => [OptionType::Identifier, 1, false];
        yield 'url' => [OptionType::Url, 'https://hooks.example.com/in', true];
        yield 'url over http' => [OptionType::Url, 'http://hooks.example.com/in', false];
        yield 'not a url' => [OptionType::Url, 'nowhere', false];
        yield 'url as integer' => [OptionType::Url, 1, false];
        yield 'choice' => [OptionType::Choice, 'b', true];
        yield 'another choice' => [OptionType::Choice, 'c', false];
        yield 'choice as integer' => [OptionType::Choice, 1, false];
        yield 'identifiers' => [OptionType::Identifiers, ['a', 'b'], true];
        yield 'identifiers with an integer' => [OptionType::Identifiers, ['a', 1], false];
        yield 'identifiers with a bad one' => [OptionType::Identifiers, ['a', 'b c'], false];
        yield 'identifiers as text' => [OptionType::Identifiers, 'a', false];
    }

    public function testARequiredOptionMustBeGiven(): void
    {
        $spec = new OptionSpec('o', OptionType::Text, true, 'Label');

        self::assertSame('The option "o" is required.', $spec->problem(Options::none()));
        self::assertSame('The option "o" is required.', $spec->problem(Options::of(['o' => null])));
        self::assertNull(new OptionSpec('o', OptionType::Text)->problem(Options::of(['o' => null])));
        self::assertSame('Label', $spec->label);
    }

    public function testADescriptionListsEveryProblem(): void
    {
        $description = new ComponentDescription('x.y', 'X', [new OptionSpec('a', OptionType::Integer, true), new OptionSpec('b', OptionType::Text)], true);

        self::assertSame(['The option "a" is required.', 'The option "b" is not a valid text.', '"x.y" takes no option "c".'], $description->problems(Options::of(['b' => 1, 'c' => 2])));
        self::assertSame([], $description->problems(Options::of(['a' => 1])));
        self::assertTrue($description->compensable);
        self::assertFalse(new ComponentDescription('x', 'X')->compensable);
        self::assertSame('x.y', $description->type);
    }
}
