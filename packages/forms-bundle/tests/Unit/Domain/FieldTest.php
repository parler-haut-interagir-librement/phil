<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Domain;

use Phil\FormsBundle\Domain\Exception\InvalidForm;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\ValueObject\Field;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Field::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(InvalidForm::class)]
final class FieldTest extends TestCase
{
    public function testAFieldIsDefined(): void
    {
        $field = Field::of('message', ' Votre message ', FieldType::Textarea, true);

        self::assertSame(['message', 'Votre message', FieldType::Textarea, true, [], 5000], [$field->name, $field->label, $field->type, $field->required, $field->choices, $field->maxLength]);
        self::assertSame(['name' => 'message', 'label' => 'Votre message', 'type' => 'textarea', 'required' => true, 'choices' => [], 'max_length' => 5000], $field->toArray());
        self::assertEquals($field, Field::fromArray($field->toArray()));
        self::assertSame([false, 255, 255, 255, null, null, null], [
            Field::of('a', 'A', FieldType::Text)->required,
            Field::of('a', 'A', FieldType::Text)->maxLength,
            Field::of('a', 'A', FieldType::Email)->maxLength,
            Field::of('a', 'A', FieldType::Url)->maxLength,
            Field::of('a', 'A', FieldType::Date)->maxLength,
            Field::of('a', 'A', FieldType::Checkbox)->maxLength,
            Field::of('a', 'A', FieldType::Choice, choices: ['x'])->maxLength,
        ]);
        self::assertSame([1, 10_000, ['oui', 'non']], [
            Field::of('a', 'A', FieldType::Text, maxLength: 1)->maxLength,
            Field::of('a', 'A', FieldType::Textarea, maxLength: 10_000)->maxLength,
            Field::of('a', 'A', FieldType::Choice, choices: ['oui', 'non'])->choices,
        ]);
        self::assertSame(['a'.str_repeat('b', 63), 'a_1'], [Field::of('a'.str_repeat('b', 63), 'A', FieldType::Text)->name, Field::of('a_1', 'A', FieldType::Text)->name]);
        self::assertSame(str_repeat('é', 255), Field::of('a', str_repeat('é', 255), FieldType::Text)->label);
    }

    public function testADefinitionAgainstTheRulesIsRefused(): void
    {
        foreach ([
            [static fn (): Field => Field::of('', 'A', FieldType::Text), 'A field name is 1 to 64 lower-case letters, digits or "_", starting with a letter; "" is not.'],
            [static fn (): Field => Field::of('1a', 'A', FieldType::Text), 'A field name is 1 to 64 lower-case letters, digits or "_", starting with a letter; "1a" is not.'],
            [static fn (): Field => Field::of('A', 'A', FieldType::Text), 'A field name is 1 to 64 lower-case letters, digits or "_", starting with a letter; "A" is not.'],
            [static fn (): Field => Field::of("a\n", 'A', FieldType::Text), "A field name is 1 to 64 lower-case letters, digits or \"_\", starting with a letter; \"a\n\" is not."],
            [static fn (): Field => Field::of('a'.str_repeat('b', 64), 'A', FieldType::Text), 'A field name is 1 to 64 lower-case letters, digits or "_", starting with a letter; "a'.str_repeat('b', 64).'" is not.'],
            [static fn (): Field => Field::of('a', ' ', FieldType::Text), 'The label of "a" is 1 to 255 characters.'],
            [static fn (): Field => Field::of('a', str_repeat('é', 256), FieldType::Text), 'The label of "a" is 1 to 255 characters.'],
            [static fn (): Field => Field::of('a', 'A', FieldType::Choice), 'A choice field has choices, and only a choice field: "a".'],
            [static fn (): Field => Field::of('a', 'A', FieldType::Text, choices: ['x']), 'A choice field has choices, and only a choice field: "a".'],
            [static fn (): Field => Field::of('a', 'A', FieldType::Choice, choices: ['x', 'y', 'x']), 'The choices of "a" are each given once.'],
            [static fn (): Field => Field::of('a', 'A', FieldType::Text, maxLength: 0), 'A text field takes 1 to 10000 characters, and only a text field has a length: "a".'],
            [static fn (): Field => Field::of('a', 'A', FieldType::Text, maxLength: 10_001), 'A text field takes 1 to 10000 characters, and only a text field has a length: "a".'],
            [static fn (): Field => Field::of('a', 'A', FieldType::Date, maxLength: 10), 'A text field takes 1 to 10000 characters, and only a text field has a length: "a".'],
        ] as [$call, $message]) {
            try {
                $call();
                self::fail($message);
            } catch (InvalidForm $invalidForm) {
                self::assertSame($message, $invalidForm->getMessage());
            }
        }
    }

    public function testValuesAreKeptOrRefused(): void
    {
        $cases = [
            'text' => [Field::of('a', 'A', FieldType::Text), ' Ada ', 'Ada', null],
            'empty' => [Field::of('a', 'A', FieldType::Text), '  ', null, null],
            'missing' => [Field::of('a', 'A', FieldType::Text), null, null, null],
            'required' => [Field::of('a', 'A', FieldType::Text, true), ' ', null, 'required'],
            'required, missing' => [Field::of('a', 'A', FieldType::Text, true), null, null, 'required'],
            'not text' => [Field::of('a', 'A', FieldType::Text), ['x'], null, 'type'],
            'long enough' => [Field::of('a', 'A', FieldType::Text, maxLength: 3), 'ééé', 'ééé', null],
            'too long' => [Field::of('a', 'A', FieldType::Text, maxLength: 3), 'éééé', 'éééé', 'too_long'],
            'email' => [Field::of('a', 'A', FieldType::Email), 'ada@example.com', 'ada@example.com', null],
            'not an email' => [Field::of('a', 'A', FieldType::Email), 'ada', 'ada', 'email'],
            'url' => [Field::of('a', 'A', FieldType::Url), 'HTTPS://example.com/a?b', 'HTTPS://example.com/a?b', null],
            'http' => [Field::of('a', 'A', FieldType::Url), 'http://example.com', 'http://example.com', null],
            'not a url' => [Field::of('a', 'A', FieldType::Url), 'ftp://example.com', 'ftp://example.com', 'url'],
            'no host' => [Field::of('a', 'A', FieldType::Url), 'https:///a', 'https:///a', 'url'],
            'url with space' => [Field::of('a', 'A', FieldType::Url), 'https://exa mple.com', 'https://exa mple.com', 'url'],
            'url, then more' => [Field::of('a', 'A', FieldType::Url), 'x https://example.com', 'x https://example.com', 'url'],
            'url, line after' => [Field::of('a', 'A', FieldType::Url), "https://example.com\nx", "https://example.com\nx", 'url'],
            'date' => [Field::of('a', 'A', FieldType::Date), '2026-02-28', '2026-02-28', null],
            'no such date' => [Field::of('a', 'A', FieldType::Date), '2026-02-30', '2026-02-30', 'date'],
            'no such month' => [Field::of('a', 'A', FieldType::Date), '2026-13-01', '2026-13-01', 'date'],
            'leap day' => [Field::of('a', 'A', FieldType::Date), '2028-02-29', '2028-02-29', null],
            'not a date' => [Field::of('a', 'A', FieldType::Date), '28/02/2026', '28/02/2026', 'date'],
            'date, then more' => [Field::of('a', 'A', FieldType::Date), "2026-02-28\n", '2026-02-28', null],
            'date, after text' => [Field::of('a', 'A', FieldType::Date), 'x2026-02-28', 'x2026-02-28', 'date'],
            'date, before text' => [Field::of('a', 'A', FieldType::Date), '2026-02-28x', '2026-02-28x', 'date'],
            'choice' => [Field::of('a', 'A', FieldType::Choice, choices: ['oui', 'non']), 'non', 'non', null],
            'not a choice' => [Field::of('a', 'A', FieldType::Choice, choices: ['oui', 'non']), 'peut-être', 'peut-être', 'choice'],
            'ticked' => [Field::of('a', 'A', FieldType::Checkbox), true, true, null],
            'ticked, by form' => [Field::of('a', 'A', FieldType::Checkbox), '1', true, null],
            'not ticked' => [Field::of('a', 'A', FieldType::Checkbox), false, false, null],
            'not ticked, missing' => [Field::of('a', 'A', FieldType::Checkbox), null, false, null],
            'not ticked, other' => [Field::of('a', 'A', FieldType::Checkbox), 'yes', false, null],
            'consent' => [Field::of('a', 'A', FieldType::Checkbox, true), true, true, null],
            'no consent' => [Field::of('a', 'A', FieldType::Checkbox, true), false, false, 'required'],
        ];
        $expected = [];
        $actual = [];

        foreach ($cases as $case => [$field, $submitted, $value, $error]) {
            $expected[$case] = ['value' => $value, 'error' => $error];
            $actual[$case] = $field->accept($submitted);
        }

        self::assertSame($expected, $actual);
    }
}
