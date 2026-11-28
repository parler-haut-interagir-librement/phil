<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderContext;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidVariables;
use Phil\EmailTemplatesBundle\Domain\Exception\RenderingFailed;
use Phil\EmailTemplatesBundle\Domain\Schema\Variable;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableSchema;
use Phil\EmailTemplatesBundle\Domain\Schema\VariableType;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

/**
 * The edges of the limits, the exact text of the messages and what each early exit leaves undone.
 */
#[CoversClass(Variable::class)]
#[CoversClass(VariableSchema::class)]
#[CoversClass(TemplateDefinition::class)]
#[CoversClass(TemplateKey::class)]
#[CoversClass(InvalidTemplate::class)]
#[CoversClass(InvalidVariables::class)]
#[CoversClass(RenderingFailed::class)]
#[CoversClass(RenderContext::class)]
final class DomainBoundariesTest extends TestCase
{
    public function testAVariableIsOptionalUnlessItIsDeclaredRequired(): void
    {
        $variable = new Variable('name', VariableType::String);

        self::assertFalse($variable->required);
        self::assertSame(['type' => 'string'], $variable->toArray());
    }

    public function testASampleTextIsMadeFromTheNameAndCutInCharacters(): void
    {
        self::assertSame('First name', new Variable('first_name', VariableType::String)->sampleValue());
        self::assertSame('é é', new Variable('é_é_é', VariableType::String, maxLength: 3)->sampleValue());
    }

    public function testASampleObjectWithoutFieldsIsEmpty(): void
    {
        self::assertSame([], new Variable('who', VariableType::Object)->sampleValue());
    }

    public function testAViolationUnderAPathIsNotRepeatedAtThePath(): void
    {
        $schema = VariableSchema::fromArray(['who' => ['type' => 'object', 'fields' => ['age' => ['type' => 'integer']]]]);

        try {
            $schema->validate(['who' => ['age' => 'old']]);
            self::fail('The values are refused.');
        } catch (InvalidVariables $invalidVariables) {
            self::assertSame(['who.age' => 'must be an integer'], $invalidVariables->violations);
        }
    }

    public function testAViolationOfASiblingPathDoesNotHideTheViolationOfThePath(): void
    {
        $variable = new Variable('who', VariableType::Object);
        $violations = ['whom.age' => 'must be an integer'];

        self::assertNull($variable->normalize('nobody', 'who', $violations));
        self::assertSame(['whom.age' => 'must be an integer', 'who' => 'must be a map of its fields'], $violations);
    }

    public function testTheMessageOfAnInvalidVariablesEndsWithAFullStop(): void
    {
        self::assertSame('The variables do not match the schema: "a" is required; "b" is wrong.', InvalidVariables::withViolations(['a' => 'is required', 'b' => 'is wrong'])->getMessage());
    }

    public function testATextIsMeasuredInCharacters(): void
    {
        $violations = [];

        self::assertSame('ééé', new Variable('t', VariableType::String, maxLength: 3)->normalize('ééé', 't', $violations));
        self::assertSame([], $violations);
        self::assertNull(new Variable('t', VariableType::String, maxLength: 3)->normalize('éééé', 't', $violations));
    }

    public function testAnIntegerAndANumberRefuseATrailingLineBreak(): void
    {
        $violations = [];

        self::assertNull(new Variable('n', VariableType::Integer)->normalize("12\n", 'n', $violations));
        self::assertSame(['n' => 'must be an integer'], $violations);
        $violations = [];
        self::assertNull(new Variable('n', VariableType::Number)->normalize("1.5\n", 'n', $violations));
        self::assertSame(['n' => 'must be a number'], $violations);
        self::assertSame(12, new Variable('n', VariableType::Integer)->normalize('12', 'n', $violations));
    }

    public function testADateOfFortyCharactersIsRead(): void
    {
        $violations = [];
        $variable = new Variable('at', VariableType::Datetime);
        $forty = '2026-01-15T18:30:00America/Port_of_Spain';

        self::assertSame(40, \strlen($forty));
        self::assertInstanceOf(DateTimeImmutable::class, $variable->normalize($forty, 'at', $violations));
        self::assertNull($variable->normalize($forty.'x', 'at', $violations));
    }

    public function testAnObjectIsNeitherAnAddressNorAnEmail(): void
    {
        $violations = [];
        $address = new class implements Stringable {
            public function __toString(): string
            {
                return 'https://example.com/a';
            }
        };
        $mail = new class implements Stringable {
            public function __toString(): string
            {
                return 'ada@example.com';
            }
        };

        self::assertNull(new Variable('u', VariableType::Url)->normalize(new stdClass(), 'u', $violations));
        self::assertNull(new Variable('e', VariableType::Email)->normalize(new stdClass(), 'e', $violations));
        self::assertSame(['u' => 'must be an absolute http or https address', 'e' => 'must be an email address'], $violations);
        self::assertSame('https://example.com/a', new Variable('u', VariableType::Url)->normalize($address, 'u', $violations));
        self::assertSame('ada@example.com', new Variable('e', VariableType::Email)->normalize($mail, 'e', $violations));
    }

    public function testEveryInvalidNameAndEveryReservedNameIsReported(): void
    {
        try {
            VariableSchema::fromArray(['A' => ['type' => 'string'], 'B' => ['type' => 'string'], 'email' => ['type' => 'string'], 'loop' => ['type' => 'string'], 'ok' => 'text', 'last' => 'text']);
            self::fail('The schema is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertSame([
                '"A" is not a variable name: a lowercase letter or "_", then up to 63 lowercase letters, digits or "_".',
                '"B" is not a variable name: a lowercase letter or "_", then up to 63 lowercase letters, digits or "_".',
                '"email" is a name the template keeps for itself.',
                '"loop" is a name the template keeps for itself.',
                '"ok" must be declared as a map, with at least its "type".',
                '"last" must be declared as a map, with at least its "type".',
            ], $invalidTemplate->errors);
        }
    }

    public function testATooDeepCompositeIsReportedAloneAndItsInsidesAreNotRead(): void
    {
        $deep = ['type' => 'object', 'fields' => ['x' => ['type' => 'integer', 'required' => 'yes']]];
        $spec = ['a' => ['type' => 'object', 'fields' => ['b' => ['type' => 'object', 'fields' => ['c' => ['type' => 'object', 'fields' => ['d' => $deep]]]]]]];

        try {
            VariableSchema::fromArray($spec);
            self::fail('The schema is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertSame(['"a.b.c.d" is a object nested more than 3 levels deep.'], $invalidTemplate->errors);
        }
    }

    public function testTheValuesAreNotCheckedWhenAnOptionIsRefused(): void
    {
        try {
            VariableSchema::fromArray(['a' => ['type' => 'integer', 'required' => 'yes', 'default' => 'x', 'sample' => 'y']]);
            self::fail('The schema is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertSame(['"a": "required" is true or false.'], $invalidTemplate->errors);
        }
    }

    public function testTheLimitsOfTheOptionsAreInclusive(): void
    {
        $description = str_repeat('é', 500);
        $schema = VariableSchema::fromArray([
            'a' => ['type' => 'string', 'description' => $description, 'max_length' => 100_000],
            'b' => ['type' => 'list', 'items' => ['type' => 'string'], 'max_items' => 1000],
            'c' => ['type' => 'string', 'max_length' => 1],
        ]);

        self::assertSame($description, $schema->variables['a']->description);
        self::assertSame(100_000, $schema->variables['a']->maxLength);
        self::assertSame(1000, $schema->variables['b']->maxItems);
        self::assertSame(1, $schema->variables['c']->maxLength);

        foreach ([
            ['type' => 'string', 'description' => str_repeat('a', 501)],
            ['type' => 'list', 'items' => ['type' => 'string'], 'max_items' => 1001],
            ['type' => 'string', 'max_length' => 100_001],
            ['type' => 'string', 'max_length' => 0],
        ] as $declaration) {
            try {
                VariableSchema::fromArray(['v' => $declaration]);
                self::fail('The schema is refused.');
            } catch (InvalidTemplate $invalidTemplate) {
                self::assertCount(1, $invalidTemplate->errors);
            }
        }
    }

    public function testTheErrorsOfTheFormatAndThoseOfTheSchemaAreAllListed(): void
    {
        try {
            TemplateDefinition::fromArray(['subject' => 'Hi', 'body' => 'x', 'format' => 'rtf', 'variables' => ['A' => ['type' => 'string'], 'B' => ['type' => 'string']]]);
            self::fail('The template is refused.');
        } catch (InvalidTemplate $invalidTemplate) {
            self::assertSame([
                'The format is "html" or "markdown".',
                '"A" is not a variable name: a lowercase letter or "_", then up to 63 lowercase letters, digits or "_".',
                '"B" is not a variable name: a lowercase letter or "_", then up to 63 lowercase letters, digits or "_".',
            ], $invalidTemplate->errors);
        }
    }

    public function testAKeyIsShownShortAndInCharacters(): void
    {
        self::assertSame('a'.str_repeat('b', 79).'…', TemplateKey::shown('a'.str_repeat('b', 80).'c'));
        self::assertSame(str_repeat('é', 50), TemplateKey::shown(str_repeat('é', 50)));
        self::assertSame(str_repeat('é', 80).'…', TemplateKey::shown(str_repeat('é', 81)));
        self::assertSame(str_repeat('a', 80), TemplateKey::shown(str_repeat('a', 80)));
    }

    public function testARenderingFailureHasNoCode(): void
    {
        self::assertSame(0, RenderingFailed::because('No.')->getCode());
    }

    public function testTheContextIsRenderedAsAMap(): void
    {
        $context = new RenderContext(TemplateKey::fromString('a'), Site::fromString('s'), Locale::fromString('fr'), ['vip']);

        self::assertSame(['key' => 'a', 'site' => 's', 'locale' => 'fr', 'segments' => ['vip']], $context->toArray());
    }

    public function testTheMessageOfAnInvalidTemplateListsTheReasonsGivenAsArguments(): void
    {
        $invalid = InvalidTemplate::because('One.', ...['x' => 'Two.']);

        self::assertSame(['One.', 'Two.'], $invalid->errors);
    }
}
