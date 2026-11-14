<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Unit\Domain;

use Closure;
use Phil\SettingsBundle\Domain\Exception\InvalidSetting;
use Phil\SettingsBundle\Domain\Exception\InvalidSettingValue;
use Phil\SettingsBundle\Domain\Setting\Keys;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SettingDefinition::class)]
#[CoversClass(SectionDefinition::class)]
#[CoversClass(Keys::class)]
#[CoversClass(InvalidSetting::class)]
#[CoversClass(InvalidSettingValue::class)]
final class SettingDefinitionTest extends TestCase
{
    public function testEachTypeHasAnImplicitDefault(): void
    {
        self::assertFalse(new SettingDefinition('a.flag', SettingType::Boolean)->default);
        self::assertSame(0, new SettingDefinition('a.count', SettingType::Integer)->default);
        self::assertSame(3, new SettingDefinition('a.count', SettingType::Integer, min: 3)->default);
        self::assertSame(-4, new SettingDefinition('a.count', SettingType::Integer, max: -4)->default);
        self::assertSame(0, new SettingDefinition('a.count', SettingType::Integer, max: 5)->default);
        self::assertSame('', new SettingDefinition('a.name', SettingType::Text)->default);
        self::assertSame('one', new SettingDefinition('a.pick', SettingType::Choice, choices: ['one', 'two'])->default);
        self::assertSame([], new SettingDefinition('a.picks', SettingType::Choices, choices: ['one', 'two'])->default);
    }

    public function testTheDeclarationIsKept(): void
    {
        $definition = new SettingDefinition('display.motion', SettingType::Choice, 'reduce', Scope::User, 'display', 'motion', 'motion.help', 'phil_settings', ['system', 'reduce'], anonymous: true, htmlAttribute: 'motion', before: ['display.theme'], after: ['display.contrast']);

        self::assertSame(['display.motion', SettingType::Choice, 'reduce', Scope::User, 'display', 'motion', 'motion.help', 'phil_settings', ['system', 'reduce'], true, 'motion', ['display.theme'], ['display.contrast']], [
            $definition->key, $definition->type, $definition->default, $definition->scope, $definition->section, $definition->label, $definition->help, $definition->translationDomain, $definition->choices, $definition->anonymous, $definition->htmlAttribute, $definition->before, $definition->after,
        ]);

        $site = new SettingDefinition('site.motto', SettingType::Text, 'Hi', Scope::Site, maxLength: 10, pattern: '/^H/', role: 'ROLE_EDITOR');
        self::assertSame(['general', 'site.motto.label', null, 'messages', 10, '/^H/', 'ROLE_EDITOR', false, null, [], []], [
            $site->section, $site->label, $site->help, $site->translationDomain, $site->maxLength, $site->pattern, $site->role, $site->anonymous, $site->htmlAttribute, $site->before, $site->after,
        ]);
        self::assertSame([1, 9], [new SettingDefinition('a.count', SettingType::Integer, 5, min: 1, max: 9)->min, new SettingDefinition('a.count', SettingType::Integer, 5, min: 1, max: 9)->max]);
        self::assertSame(255, new SettingDefinition('a.name', SettingType::Text)->maxLength);
    }

    public function testASectionIsKept(): void
    {
        $section = new SectionDefinition('author', 'author.title', 'author.help', 'app', ['privacy'], ['general']);

        self::assertSame(['author', 'author.title', 'author.help', 'app', ['privacy'], ['general']], [$section->key, $section->label, $section->help, $section->translationDomain, $section->before, $section->after]);
        self::assertSame(['section.author', null, 'messages', [], []], [new SectionDefinition('author')->label, new SectionDefinition('author')->help, new SectionDefinition('author')->translationDomain, new SectionDefinition('author')->before, new SectionDefinition('author')->after]);
    }

    /**
     * @return iterable<string, array{mixed, SettingType, array<string, mixed>, mixed}>
     */
    public static function accepted(): iterable
    {
        yield 'true' => [true, SettingType::Boolean, [], true];
        yield 'false' => [false, SettingType::Boolean, [], false];
        yield 'an integer' => [7, SettingType::Integer, [], 7];
        yield 'the minimum' => [1, SettingType::Integer, ['min' => 1, 'max' => 3], 1];
        yield 'the maximum' => [3, SettingType::Integer, ['min' => 1, 'max' => 3], 3];
        yield 'a text' => ['Élodie', SettingType::Text, [], 'Élodie'];
        yield 'a text as long as allowed' => ['éééé', SettingType::Text, ['maxLength' => 4], 'éééé'];
        yield 'a text that matches' => ['22:00', SettingType::Text, ['pattern' => '/^\d\d:\d\d$/'], '22:00'];
        yield 'a choice' => ['two', SettingType::Choice, ['choices' => ['one', 'two']], 'two'];
        yield 'choices in their order, once' => [['three', 'one', 'three'], SettingType::Choices, ['choices' => ['one', 'two', 'three']], ['one', 'three']];
        yield 'no choice' => [[], SettingType::Choices, ['choices' => ['one']], []];
    }

    /**
     * @param array<string, mixed> $rules
     */
    #[DataProvider('accepted')]
    public function testAValueThatKeepsTheRulesIsNormalized(mixed $value, SettingType $type, array $rules, mixed $expected): void
    {
        $definition = $this->definition($type, $rules);

        self::assertSame($expected, $definition->normalize($value));
        self::assertSame($expected, $definition->decode($definition->encode($definition->normalize($value))));
    }

    /**
     * @return iterable<string, array{mixed, SettingType, array<string, mixed>, string, string, array<string, int|string>}>
     */
    public static function refused(): iterable
    {
        yield 'a string for a boolean' => ['1', SettingType::Boolean, [], 'type', 'a boolean is expected.', []];
        yield 'a string for an integer' => ['7', SettingType::Integer, [], 'type', 'an integer is expected.', []];
        yield 'below the minimum' => [0, SettingType::Integer, ['min' => 1], 'range', '0 is outside its range.', ['%min%' => 1, '%max%' => \PHP_INT_MAX]];
        yield 'above the maximum' => [4, SettingType::Integer, ['max' => 3], 'range', '4 is outside its range.', ['%min%' => \PHP_INT_MIN, '%max%' => 3]];
        yield 'an integer for a text' => [7, SettingType::Text, [], 'type', 'a string is expected.', []];
        yield 'not UTF-8' => ["\xFF", SettingType::Text, [], 'encoding', 'it is not one line of UTF-8 text.', []];
        yield 'two lines' => ["a\nb", SettingType::Text, [], 'encoding', 'it is not one line of UTF-8 text.', []];
        yield 'too long' => ['ééééé', SettingType::Text, ['maxLength' => 4], 'length', 'it is longer than 4 characters.', ['%max%' => 4]];
        yield 'not matching' => ['22h', SettingType::Text, ['pattern' => '/^\d\d:\d\d$/'], 'pattern', 'it does not match the pattern.', []];
        yield 'not a choice' => ['three', SettingType::Choice, ['choices' => ['one']], 'choice', 'it is not one of the choices.', []];
        yield 'a list for a choice' => [['one'], SettingType::Choice, ['choices' => ['one']], 'choice', 'it is not one of the choices.', []];
        yield 'a string for choices' => ['one', SettingType::Choices, ['choices' => ['one']], 'type', 'a list of choices is expected.', []];
        yield 'a map for choices' => [['a' => 'one'], SettingType::Choices, ['choices' => ['one']], 'type', 'a list of choices is expected.', []];
        yield 'an unknown among choices' => [['one', 'two'], SettingType::Choices, ['choices' => ['one']], 'choice', 'one of them is not one of the choices.', []];
        yield 'an integer among choices' => [['one', 1], SettingType::Choices, ['choices' => ['one', '1']], 'choice', 'one of them is not one of the choices.', []];
    }

    /**
     * @param array<string, mixed>      $rules
     * @param array<string, int|string> $parameters
     */
    #[DataProvider('refused')]
    public function testAValueThatBreaksTheRulesIsRefused(mixed $value, SettingType $type, array $rules, string $reason, string $message, array $parameters): void
    {
        try {
            $this->definition($type, $rules)->normalize($value);
            self::fail('The value is accepted.');
        } catch (InvalidSettingValue $invalidSettingValue) {
            self::assertSame('The value of the setting "a.setting" is refused: '.$message, $invalidSettingValue->getMessage());
            self::assertSame(['a.setting', 'phil_settings.error.'.$reason, $parameters], [$invalidSettingValue->key, $invalidSettingValue->reason, $invalidSettingValue->parameters]);
        }
    }

    public function testAStoredValueThatNoLongerKeepsTheRulesIsNull(): void
    {
        $definition = new SettingDefinition('a.pick', SettingType::Choice, choices: ['one', 'two']);

        self::assertSame('two', $definition->decode('"two"'));
        self::assertNull($definition->decode('"three"'));
        self::assertNull($definition->decode('{"two"'));
        self::assertNull($definition->decode('[["two"]]'), 'deeper than two levels');
        self::assertSame('["one","two"]', new SettingDefinition('a.picks', SettingType::Choices, choices: ['one', 'two'])->encode(['one', 'two']));
        self::assertSame('"é/è"', new SettingDefinition('a.name', SettingType::Text)->encode('é/è'));
        self::assertSame(['two'], new SettingDefinition('a.picks', SettingType::Choices, choices: ['one', 'two'])->decode('["two"]'));
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function contradictions(): iterable
    {
        yield 'a malformed key' => [static fn (): SettingDefinition => new SettingDefinition('motion', SettingType::Boolean), '"motion" is not the key of a setting: dotted lowercase words, such as "display.motion", of at most 128 characters.'];
        yield 'an uppercase key' => [static fn (): SettingDefinition => new SettingDefinition('Display.motion', SettingType::Boolean), '"Display.motion" is not the key of a setting'];
        yield 'a key ending in a dot' => [static fn (): SettingDefinition => new SettingDefinition('display.', SettingType::Boolean), '"display." is not the key of a setting'];
        yield 'a key with a newline' => [static fn (): SettingDefinition => new SettingDefinition("display.motion\n", SettingType::Boolean), 'is not the key of a setting'];
        yield 'a key too long' => [static fn (): SettingDefinition => new SettingDefinition('a.'.str_repeat('b', 127), SettingType::Boolean), 'is not the key of a setting'];
        yield 'a malformed section' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Boolean, section: 'my-section'), '"my-section" is not the key of a section: a lowercase word, such as "display", of at most 64 characters.'];
        yield 'a section too long' => [static fn (): SectionDefinition => new SectionDefinition('a'.str_repeat('b', 64)), 'is not the key of a section'];
        yield 'a section with a newline' => [static fn (): SectionDefinition => new SectionDefinition("author\n"), 'is not the key of a section'];
        yield 'a choice without choices' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Choice), 'The setting "a.b" is a choice: it offers choices.'];
        yield 'choices without choices' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Choices), 'The setting "a.b" is a choices: it offers choices.'];
        yield 'a text with choices' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Text, choices: ['x']), 'The setting "a.b" is a text: it offers no choices.'];
        yield 'a choice twice' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Choice, choices: ['x', 'x']), 'The choices of the setting "a.b" are at most 100, each once.'];
        yield 'too many choices' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Choice, choices: array_map(static fn (int $i): string => 'c'.$i, range(1, 101))), 'The choices of the setting "a.b" are at most 100, each once.'];
        yield 'a malformed choice' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Choice, choices: ['x', 'Y']), '"Y" is not a choice of the setting "a.b": lowercase letters, digits, "_" and "-", of at most 64 characters.'];
        yield 'a choice with a newline' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Choice, choices: ["x\n"]), 'is not a choice of the setting "a.b"'];
        yield 'a minimum on a text' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Text, min: 1), 'Only an integer has a minimum and a maximum, not the setting "a.b".'];
        yield 'a maximum on a boolean' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Boolean, max: 1), 'Only an integer has a minimum and a maximum, not the setting "a.b".'];
        yield 'a minimum above the maximum' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Integer, 1, min: 2, max: 1), 'The minimum of the setting "a.b" is above its maximum.'];
        yield 'no text at all' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Text, maxLength: 0), 'The longest text of the setting "a.b" is between 1 and 2000 characters.'];
        yield 'too long a text' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Text, maxLength: 2001), 'The longest text of the setting "a.b" is between 1 and 2000 characters.'];
        yield 'a pattern on an integer' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Integer, pattern: '/x/'), 'The pattern of the setting "a.b" is a regular expression of a text.'];
        yield 'a pattern that does not compile' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Text, pattern: '/(/'), 'The pattern of the setting "a.b" is a regular expression of a text.'];
        yield 'a site setting for visitors' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Boolean, scope: Scope::Site, anonymous: true), 'The setting "a.b" is the site\'s: no visitor keeps it.'];
        yield 'a role on a user setting' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Boolean, role: 'ROLE_ADMIN'), 'The setting "a.b" is each user\'s own: no role changes it.'];
        yield 'a text for visitors' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Text, anonymous: true), 'A visitor keeps a boolean or a choice in a cookie, not the setting "a.b".'];
        yield 'an integer for visitors' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Integer, anonymous: true), 'A visitor keeps a boolean or a choice in a cookie, not the setting "a.b".'];
        yield 'an attribute on a boolean' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Boolean, htmlAttribute: 'b'), 'The HTML attribute of the setting "a.b" is the name after "data-" of a choice.'];
        yield 'a malformed attribute' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Choice, choices: ['x'], htmlAttribute: 'data b'), 'The HTML attribute of the setting "a.b" is the name after "data-" of a choice.'];
        yield 'an attribute with a newline' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Choice, choices: ['x'], htmlAttribute: "b\n"), 'The HTML attribute of the setting "a.b"'];
        yield 'a default of the wrong type' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Boolean, 'yes'), 'The default of the setting "a.b" breaks its own rules: The value of the setting "a.b" is refused: a boolean is expected.'];
        yield 'a default outside the range' => [static fn (): SettingDefinition => new SettingDefinition('a.b', SettingType::Integer, default: 0, min: 1, max: 3), 'The default of the setting "a.b" breaks its own rules'];
    }

    /**
     * @param Closure(): mixed $declare
     */
    #[DataProvider('contradictions')]
    public function testADeclarationThatContradictsItselfIsRefused(Closure $declare, string $message): void
    {
        $this->expectException(InvalidSetting::class);
        $this->expectExceptionMessageIsOrContains($message);

        $declare();
    }

    public function testAPatternThatDoesNotCompileSaysNothingMore(): void
    {
        $this->expectOutputString('');

        try {
            new SettingDefinition('a.b', SettingType::Text, pattern: '/[/');
            self::fail('The pattern is accepted.');
        } catch (InvalidSetting $invalidSetting) {
            self::assertSame('The pattern of the setting "a.b" is a regular expression of a text.', $invalidSetting->getMessage());
        }
    }

    public function testTheBoundsAreAllowed(): void
    {
        self::assertSame(2000, new SettingDefinition('a.b', SettingType::Text, maxLength: 2000)->maxLength);
        self::assertSame(1, new SettingDefinition('a.b', SettingType::Text, maxLength: 1)->maxLength);
        self::assertCount(100, new SettingDefinition('a.b', SettingType::Choice, choices: array_map(static fn (int $i): string => 'c'.$i, range(1, 100)))->choices);
        self::assertSame(2, new SettingDefinition('a.b', SettingType::Integer, 2, min: 2, max: 2)->default);
        self::assertSame('a.'.str_repeat('b', 126), new SettingDefinition('a.'.str_repeat('b', 126), SettingType::Boolean)->key);
        self::assertSame('a'.str_repeat('b', 63), new SectionDefinition('a'.str_repeat('b', 63))->key);
        self::assertSame('b'.str_repeat('c', 31), new SettingDefinition('a.b', SettingType::Choice, choices: ['x'], htmlAttribute: 'b'.str_repeat('c', 31))->htmlAttribute);
        self::assertTrue(new SettingDefinition('a.b', SettingType::Choice, choices: ['x'], anonymous: true)->anonymous);
        self::assertTrue(new SettingDefinition('a.b', SettingType::Boolean, anonymous: true)->anonymous);
        self::assertSame('ROLE_X', new SettingDefinition('a.b', SettingType::Boolean, scope: Scope::Site, role: 'ROLE_X')->role);
        self::assertSame('x-1_y', new SettingDefinition('a.b', SettingType::Choice, choices: ['x-1_y'])->default);
        self::assertSame('m', new SettingDefinition('a.b', SettingType::Choice, choices: ['m'])->default);
        self::assertSame('a_1.b_2.c', new SettingDefinition('a_1.b_2.c', SettingType::Boolean)->key);
    }

    /**
     * @param array<string, mixed> $rules
     */
    private function definition(SettingType $type, array $rules): SettingDefinition
    {
        $choices = $rules['choices'] ?? [];
        $min = $rules['min'] ?? null;
        $max = $rules['max'] ?? null;
        $maxLength = $rules['maxLength'] ?? 255;
        $pattern = $rules['pattern'] ?? null;
        self::assertIsArray($choices);
        self::assertTrue(null === $min || \is_int($min));
        self::assertTrue(null === $max || \is_int($max));
        self::assertIsInt($maxLength);
        self::assertTrue(null === $pattern || \is_string($pattern));

        /** @var list<string> $choices */
        $default = match ($type) {
            SettingType::Text => null === $pattern ? '' : '22:00',
            default => null,
        };

        return new SettingDefinition('a.setting', $type, $default, choices: $choices, min: $min, max: $max, maxLength: $maxLength, pattern: $pattern);
    }
}
