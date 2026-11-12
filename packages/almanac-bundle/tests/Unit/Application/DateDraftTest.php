<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle\Tests\Unit\Application;

use Phil\Almanac\Definition;
use Phil\Almanac\Rule\RuleParser;
use Phil\AlmanacBundle\Application\DateDraft;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

#[CoversClass(DateDraft::class)]
final class DateDraftTest extends TestCase
{
    public function testADraftOfADefinitionAndBack(): void
    {
        $definition = new Definition('oktoberfest', 'Oktoberfest', RuleParser::parse('saturday on or after 09-15'), RuleParser::parse('sunday on or after 10-03'), tags: ['fete']);
        $draft = DateDraft::of($definition);

        self::assertSame(['oktoberfest', 'Oktoberfest', 'saturday on or after 09-15', 'sunday on or after 10-03', 1, true, ['fete']], [$draft->key, $draft->title, $draft->rule, $draft->until, $draft->days, $draft->allDay, $draft->tags]);
        self::assertEquals($definition, $draft->definition());
        self::assertSame([], $this->violations($draft));

        $moon = DateDraft::of(new Definition('moon', 'Moon', RuleParser::parse('full moon'), allDay: false));
        self::assertNull($moon->until);
        $moon->until = '  ';
        self::assertNull($moon->definition()->until, 'a blank until is none');
        self::assertFalse($moon->definition()->allDay);
        self::assertSame([], $this->violations($moon));
        $moon->days = null;
        self::assertSame(1, $moon->definition()->days, 'a field left empty lasts a day');
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function invalidDrafts(): iterable
    {
        yield 'nothing' => [[], ['key: This value should not be blank.', 'title: This value should not be blank.', 'rule: This value should not be blank.']];
        yield 'a key in capitals' => [['key' => 'Noel'], ['key: phil_almanac.key']];
        yield 'a key and a new line' => [['key' => "noel\n"], ['key: phil_almanac.key']];
        yield 'a title too long' => [['title' => str_repeat('a', 201)], ['title: This value is too long. It should have 200 characters or less.']];
        yield 'a rule too long' => [['rule' => str_repeat('a', 201)], ['rule: phil_almanac.rule "'.str_repeat('a', 100).'" is not a rule the almanac reads.', 'rule: This value is too long. It should have 200 characters or less.']];
        yield 'an unreadable rule' => [['rule' => 'every blue moon'], ['rule: phil_almanac.rule "every blue moon" is not a rule the almanac reads.']];
        yield 'an unreadable until' => [['until' => 'never'], ['until: phil_almanac.rule "never" is not a rule the almanac reads.']];
        yield 'an until too long' => [['until' => str_repeat('a', 201)], ['until: phil_almanac.rule "'.str_repeat('a', 100).'" is not a rule the almanac reads.', 'until: This value is too long. It should have 200 characters or less.']];
        yield 'no day' => [['days' => 0], ['days: This value should be between 1 and 366.']];
        yield 'days left empty' => [['days' => null], ['days: This value should not be null.']];
        yield 'too many days' => [['days' => 367], ['days: This value should be between 1 and 366.']];
        yield 'a blank title' => [['title' => ' '], ['title: This value should not be blank.']];
        yield 'days and until' => [['days' => 2, 'until' => '12-26'], [': phil_almanac.date The date "noel" lasts 1 to 366 days, or until another rule; not both.']];
        yield 'a day at its time' => [['allDay' => false], [': phil_almanac.date Only an instant of the sky, a phase, a season or an eclipse, can be shown at its time; "noel" is 12-25.']];
        yield 'a key that starts with a dash' => [['key' => '-noel'], ['key: phil_almanac.key']];
        yield 'the longest key' => [['key' => str_repeat('a', 100)], []];
        yield 'the most days' => [['days' => 366], []];
        yield 'the most days and until' => [['days' => 366, 'until' => '12-26'], [': phil_almanac.date The date "noel" lasts 1 to 366 days, or until another rule; not both.']];
        yield 'a blank rule and an unreadable until' => [['rule' => '', 'until' => 'never'], ['until: phil_almanac.rule "never" is not a rule the almanac reads.', 'rule: This value should not be blank.']];
    }

    /**
     * @param array<string, mixed> $fields
     * @param list<string>         $expected
     */
    #[DataProvider('invalidDrafts')]
    public function testADraftIsRefused(array $fields, array $expected): void
    {
        $draft = new DateDraft();

        if ([] !== $fields) {
            $draft->key = 'noel';
            $draft->title = 'Noël';
            $draft->rule = '12-25';

            foreach ($fields as $field => $value) {
                match ($field) {
                    'key' => $draft->key = \is_string($value) ? $value : null,
                    'title' => $draft->title = \is_string($value) ? $value : null,
                    'rule' => $draft->rule = \is_string($value) ? $value : null,
                    'until' => $draft->until = \is_string($value) ? $value : null,
                    'days' => $draft->days = \is_int($value) ? $value : null,
                    default => $draft->allDay = true === $value,
                };
            }
        }

        self::assertSame($expected, $this->violations($draft));
    }

    public function testAnUnreadableRuleStopsTheRest(): void
    {
        $draft = new DateDraft();
        $draft->key = 'noel';
        $draft->title = 'Noël';
        $draft->rule = 'nope';
        $draft->until = 'nope either';
        $draft->days = 2;

        self::assertSame(['rule: phil_almanac.rule "nope" is not a rule the almanac reads.'], $this->violations($draft));
    }

    public function testABlankRuleIsOnlyBlank(): void
    {
        $draft = new DateDraft();
        $draft->key = 'noel';
        $draft->title = 'Noël';
        $draft->rule = ' ';

        self::assertSame(['rule: This value should not be blank.'], $this->violations($draft));
    }

    /**
     * @return list<string>
     */
    private function violations(DateDraft $draft): array
    {
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($draft);

        return array_map(
            static fn (ConstraintViolationInterface $violation): string => $violation->getPropertyPath().': '.$violation->getMessage().(\is_string($reason = $violation->getParameters()['{{ reason }}'] ?? null) ? ' '.$reason : ''),
            iterator_to_array($violations, false),
        );
    }
}
