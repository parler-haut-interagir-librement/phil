<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Tests\Unit\Application;

use Phil\CommerceWholesaleBundle\Application\QuantityRules;
use Phil\CommerceWholesaleBundle\Domain\Model\QuantityRule;
use Phil\CommerceWholesaleBundle\Tests\Unit\Support\InMemoryQuantityRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuantityRules::class)]
final class QuantityRulesTest extends TestCase
{
    public function testAGroupsOwnRuleComesBeforeTheRuleForEveryGroup(): void
    {
        $repository = new InMemoryQuantityRules(
            QuantityRule::of('DICE', 'wholesale', 12, null, 12),
            QuantityRule::of('DICE', null, 1, 10),
            QuantityRule::of('DICE', 'staff', 1, 2),
            QuantityRule::of('MAT', 'wholesale', 5),
        );
        $rules = new QuantityRules($repository);

        self::assertSame(12, $rules->ruleFor('DICE', 'wholesale')?->minimum());
        self::assertSame(2, $rules->ruleFor('DICE', 'staff')?->maximum());
        self::assertSame(10, $rules->ruleFor('DICE', 'retail')?->maximum());
        self::assertSame(10, $rules->ruleFor('DICE', null)?->maximum());
        self::assertSame(5, $rules->ruleFor('MAT', 'wholesale')?->minimum());
        self::assertNull($rules->ruleFor('MAT', 'retail'));
        self::assertNull($rules->ruleFor('MAT', null));
        self::assertNull($rules->ruleFor('CARDS', 'wholesale'));
        self::assertSame(8, $repository->lookups);
    }

    public function testARuleIsSetChangedAndRemoved(): void
    {
        $repository = new InMemoryQuantityRules();
        $rules = new QuantityRules($repository);

        $set = $rules->set('DICE', 'wholesale', 6, 60, 6);
        self::assertSame($set, $repository->findRule('DICE', 'wholesale'));

        $changed = $rules->set('DICE', 'wholesale', 12, null, 4);
        self::assertSame($set, $changed);
        self::assertSame([12, null, 4], [$changed->minimum(), $changed->maximum(), $changed->increment()]);
        self::assertCount(1, $repository->rules);

        $every = $rules->set('DICE', null);
        self::assertSame([null, 1, null, 1], [$every->customerGroup, $every->minimum(), $every->maximum(), $every->increment()]);
        self::assertCount(2, $repository->rules);

        $rules->remove('DICE', 'wholesale');
        $rules->remove('DICE', 'unknown');
        self::assertSame([$every], array_values($repository->rules));
    }
}
