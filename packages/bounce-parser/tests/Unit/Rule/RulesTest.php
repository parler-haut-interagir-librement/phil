<?php

declare(strict_types=1);

namespace Phil\BounceParser\Tests\Unit\Rule;

use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceKind;
use Phil\BounceParser\Exception\InvalidRule;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Rule\RuleScope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rule::class)]
#[CoversClass(Rules::class)]
#[CoversClass(InvalidRule::class)]
#[CoversClass(BounceAction::class)]
final class RulesTest extends TestCase
{
    public function testDeclaresARuleOnARegularExpression(): void
    {
        $rule = Rule::matching('full', Field::Body, '/mailbox (is )?full/i', BounceKind::Soft);

        self::assertSame('full', $rule->name);
        self::assertSame(Field::Body, $rule->field);
        self::assertSame('/mailbox (is )?full/i', $rule->pattern);
        self::assertSame(BounceKind::Soft, $rule->kind);
        self::assertSame(BounceAction::CountSoft, $rule->action);
        self::assertSame(RuleScope::Unrecognised, $rule->scope);
        self::assertNull($rule->header);
        self::assertTrue($rule->matches('The MAILBOX IS FULL'));
        self::assertFalse($rule->matches('mailbox empty'));
    }

    public function testDeclaresARuleOnAText(): void
    {
        $rule = Rule::containing('gone', Field::Header, 'a/b (c)', BounceKind::Hard, BounceAction::Suppress, RuleScope::Always, 'X-Reason');

        self::assertSame(BounceAction::Suppress, $rule->action);
        self::assertSame(RuleScope::Always, $rule->scope);
        self::assertSame('X-Reason', $rule->header);
        self::assertTrue($rule->matches('Reason: A/B (C)'));
        self::assertFalse($rule->matches('a/b c'));
        self::assertTrue(Rule::containing('é', Field::Body, 'É', BounceKind::Soft)->matches('é'));
    }

    public function testDoesNotMatchWhenThePatternFails(): void
    {
        $rule = Rule::matching('slow', Field::Body, '/(a+)+$/', BounceKind::Soft);
        $utf8 = Rule::matching('utf8', Field::Body, '/./u', BounceKind::Soft);

        self::assertFalse($rule->matches(str_repeat('a', 50_000).'b'));
        self::assertFalse($utf8->matches("\xC3"));
    }

    public function testRefusesAnInvalidRule(): void
    {
        foreach ([
            ['The pattern of the rule "r" is not a valid regular expression.', static fn (): Rule => Rule::matching('r', Field::Body, '/(/', BounceKind::Soft)],
            ['The pattern of the rule "r" is not a valid regular expression.', static fn (): Rule => Rule::matching('r', Field::Body, 'no delimiters', BounceKind::Soft)],
            ['The rule "r" looks at a header field without naming it.', static fn (): Rule => Rule::matching('r', Field::Header, '/x/', BounceKind::Soft)],
            ['The rule "r" looks for an empty text.', static fn (): Rule => Rule::containing('r', Field::Body, '', BounceKind::Soft)],
        ] as [$message, $declare]) {
            try {
                $declare();
                self::fail('The rule was accepted.');
            } catch (InvalidRule $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertTrue(Rule::matching('r', Field::Body, '/x/', BounceKind::Soft)->matches('x'));
    }

    public function testOrdersRulesByName(): void
    {
        $a = Rule::containing('a', Field::Body, 'a', BounceKind::Soft);
        $b = Rule::containing('b', Field::Body, 'b', BounceKind::Soft);
        $c = Rule::containing('c', Field::Body, 'c', BounceKind::Soft);
        $d = Rule::containing('d', Field::Body, 'd', BounceKind::Soft);
        $rules = new Rules($a, $b);

        self::assertSame(['a', 'b', 'c'], $rules->with($c)->names());
        self::assertSame(['c', 'a', 'b'], $rules->with($c, before: 'a')->names());
        self::assertSame(['a', 'c', 'b'], $rules->with($c, before: 'b')->names());
        self::assertSame(['a', 'c', 'b'], $rules->with($c, after: 'a')->names());
        self::assertSame(['a', 'b', 'c'], $rules->with($c, after: 'b')->names());
        self::assertSame(['a', 'd', 'c', 'b'], $rules->with($c, after: 'a')->with($d, before: 'c')->names());
        self::assertSame(['c', 'a', 'b'], $rules->with($c, before: 'a', after: 'b')->names());
        self::assertSame([$a, $b], iterator_to_array($rules));
        self::assertSame([], new Rules()->names());
        self::assertSame(['a'], new Rules(...['first' => $a])->names());
    }

    public function testRefusesTwoRulesOfOneNameAndAnUnknownNeighbour(): void
    {
        $a = Rule::containing('a', Field::Body, 'a', BounceKind::Soft);
        $b = Rule::containing('b', Field::Body, 'b', BounceKind::Soft);

        foreach ([
            ['A rule is already named "a".', static fn (): Rules => new Rules($a, $a)],
            ['A rule is already named "a".', static fn (): Rules => new Rules($a)->with($a)],
            ['No rule is named "x".', static fn (): Rules => new Rules($a)->with($b, before: 'x')],
            ['No rule is named "y".', static fn (): Rules => new Rules($a)->with($b, after: 'y')],
        ] as [$message, $build]) {
            try {
                $build();
                self::fail('The rules were accepted.');
            } catch (InvalidRule $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }
    }

    public function testGivesTheActionOfEachKind(): void
    {
        self::assertSame(BounceAction::CountHard, BounceAction::for(BounceKind::Hard));
        self::assertSame(BounceAction::CountSoft, BounceAction::for(BounceKind::Soft));
        self::assertSame(BounceAction::Ignore, BounceAction::for(BounceKind::AutoReply));
        self::assertSame(BounceAction::Ignore, BounceAction::for(BounceKind::Delivered));
        self::assertSame(BounceAction::Unsubscribe, BounceAction::for(BounceKind::Complaint));
        self::assertSame(BounceAction::Review, BounceAction::for(BounceKind::Feedback));
        self::assertSame(BounceAction::Review, BounceAction::for(BounceKind::Unknown));
    }
}
