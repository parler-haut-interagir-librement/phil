<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Application\Flag;

use Override;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\Evaluation;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationErrorCode;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\MemberOf;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CountingProvider;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CountingResolver;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryFlagStore;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\SwitchableResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlagEvaluator::class)]
final class FlagEvaluatorTest extends TestCase
{
    public function testTheFirstProviderThatKnowsTheFlagWins(): void
    {
        $database = $this->provider(['f' => false]);
        $configuration = $this->provider(['f' => true, 'g' => true]);
        $evaluator = new FlagEvaluator([$database, $configuration], $this->resolver());

        self::assertFalse($evaluator->isEnabled(FlagName::of('f')));
        self::assertTrue($evaluator->isEnabled(FlagName::of('g')));
    }

    public function testAFlagNoProviderKnowsIsOff(): void
    {
        $evaluation = new FlagEvaluator([$this->provider([])], $this->resolver())->evaluate(FlagName::of('missing'));

        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationReason::Error, $evaluation->reason);
        self::assertSame(EvaluationErrorCode::FlagNotFound, $evaluation->errorCode);
    }

    /**
     * A toggle in the middle of a request does not change the value halfway through it.
     */
    public function testAFlagIsEvaluatedOncePerRequest(): void
    {
        $provider = $this->provider(['f' => true]);
        $resolver = $this->resolver();
        $evaluator = new FlagEvaluator([$provider], $resolver);

        self::assertTrue($evaluator->isEnabled(FlagName::of('f')));
        $provider->flags['f'] = false;
        self::assertTrue($evaluator->isEnabled(FlagName::of('f')));
        self::assertSame(1, $provider->lookups);
    }

    /**
     * A flag read before the firewall loads the user is evaluated for the anonymous context; once the user is
     * known, the same flag is evaluated again for the user's context, never answered from the anonymous one.
     */
    public function testAContextThatChangesMidRequestGetsItsOwnEvaluation(): void
    {
        $provider = new class implements FlagProvider {
            #[Override]
            public function find(FlagName $name): FlagDefinition
            {
                return new FlagDefinition($name, true, [MemberOf::of(TargetingAttribute::Subject, ['alice'])]);
            }
        };
        $resolver = new SwitchableResolver(EvaluationContext::anonymous());
        $evaluator = new FlagEvaluator([$provider], $resolver);

        self::assertFalse($evaluator->isEnabled(FlagName::of('members-only')));

        $resolver->context = EvaluationContext::of(subject: 'alice');
        self::assertTrue($evaluator->isEnabled(FlagName::of('members-only')));
        self::assertTrue($evaluator->evaluated()['members-only']->enabled, 'the profiler shows the latest evaluation');

        $resolver->context = EvaluationContext::anonymous();
        self::assertFalse($evaluator->isEnabled(FlagName::of('members-only')));
    }

    /**
     * A context the resolver lets go is forgotten with its key: a new context, even one that takes the memory of the
     * old, is evaluated for its own attributes; two instances of the same attributes still share their evaluations.
     */
    public function testEachContextInstanceIsKeyedByItsOwnAttributes(): void
    {
        $provider = new class implements FlagProvider {
            public int $lookups = 0;

            #[Override]
            public function find(FlagName $name): FlagDefinition
            {
                ++$this->lookups;

                return new FlagDefinition($name, true, [MemberOf::of(TargetingAttribute::Subject, ['member-0', 'member-2', 'member-4'])]);
            }
        };
        $resolver = new SwitchableResolver(EvaluationContext::anonymous());
        $evaluator = new FlagEvaluator([$provider], $resolver);

        for ($index = 0; $index < 6; ++$index) {
            $resolver->context = EvaluationContext::of(subject: 'member-'.$index);
            self::assertSame(0 === $index % 2, $evaluator->isEnabled(FlagName::of('f')), 'member-'.$index);
            self::assertSame(0 === $index % 2, $evaluator->isEnabled(FlagName::of('f')), 'member-'.$index.', memoized');
        }

        $resolver->context = EvaluationContext::of(subject: 'member-2');
        self::assertTrue($evaluator->isEnabled(FlagName::of('f')));
        self::assertSame(6, $provider->lookups, 'an equal context made anew shares the evaluations of the first');
    }

    public function testAnAbsentAttributeAndAPresentOneAreTwoContexts(): void
    {
        $provider = new class implements FlagProvider {
            #[Override]
            public function find(FlagName $name): FlagDefinition
            {
                return new FlagDefinition($name, true, [MemberOf::of(TargetingAttribute::Project, ['-'])]);
            }
        };
        $resolver = new SwitchableResolver(EvaluationContext::anonymous());
        $evaluator = new FlagEvaluator([$provider], $resolver);

        self::assertFalse($evaluator->isEnabled(FlagName::of('f')));
        $resolver->context = EvaluationContext::of(project: '-');
        self::assertTrue($evaluator->isEnabled(FlagName::of('f')));
    }

    public function testTwoPresentValuesAreTwoContexts(): void
    {
        $provider = new class implements FlagProvider {
            #[Override]
            public function find(FlagName $name): FlagDefinition
            {
                return new FlagDefinition($name, true, [MemberOf::of(TargetingAttribute::Subject, ['alice'])]);
            }
        };
        $resolver = new SwitchableResolver(EvaluationContext::of(subject: 'alice'));
        $evaluator = new FlagEvaluator([$provider], $resolver);

        self::assertTrue($evaluator->isEnabled(FlagName::of('f')));
        $resolver->context = EvaluationContext::of(subject: 'bob');
        self::assertFalse($evaluator->isEnabled(FlagName::of('f')));
    }

    public function testTheDefinitionIsTheFirstProvidersOne(): void
    {
        $evaluator = new FlagEvaluator([$this->provider(['f' => false]), $this->provider(['f' => true])], $this->resolver(), new InMemoryFlagStore(['f' => true]));

        self::assertFalse($evaluator->definition(FlagName::of('f'))?->active, 'the definition is read before any stored state');
        self::assertNull($evaluator->definition(FlagName::of('missing')));
    }

    public function testResetStartsANewRequest(): void
    {
        $provider = $this->provider(['f' => true]);
        $resolver = $this->resolver();
        $evaluator = new FlagEvaluator([$provider], $resolver);

        $evaluator->isEnabled(FlagName::of('f'));
        $provider->flags['f'] = false;
        $evaluator->reset();

        self::assertFalse($evaluator->isEnabled(FlagName::of('f')));
        self::assertSame(2, $provider->lookups);
    }

    public function testTheResolvedContextReachesTheRules(): void
    {
        $provider = new class implements FlagProvider {
            #[Override]
            public function find(FlagName $name): FlagDefinition
            {
                return new FlagDefinition($name, true, [MemberOf::of(TargetingAttribute::Tenant, ['t1'])]);
            }
        };

        self::assertTrue(new FlagEvaluator([$provider], $this->resolver(EvaluationContext::of(tenant: 't1')))->isEnabled(FlagName::of('f')));
        self::assertFalse(new FlagEvaluator([$provider], $this->resolver(EvaluationContext::of(tenant: 't2')))->isEnabled(FlagName::of('f')));
    }

    /**
     * The store holds the active state, the configuration the rules: `new-checkout` at 70 % by tenant admits
     * `tenant-2` (bucket 62) and not `tenant-1` (bucket 81), before the deactivation and after the reactivation.
     */
    public function testReactivationFromTheStoreRestoresTheConfiguredRollout(): void
    {
        $configuration = new class implements FlagProvider {
            #[Override]
            public function find(FlagName $name): ?FlagDefinition
            {
                return 'new-checkout' === $name->value
                    ? new FlagDefinition($name, true, [new PercentageRollout(TargetingAttribute::Tenant, Percentage::of(70))])
                    : null;
            }
        };
        $store = new InMemoryFlagStore(['new-checkout' => true]);
        $flag = FlagName::of('new-checkout');
        $in = EvaluationContext::of(tenant: 'tenant-2');
        $out = EvaluationContext::of(tenant: 'tenant-1');

        $evaluate = static fn (EvaluationContext $context): Evaluation => new FlagEvaluator([$configuration], new CountingResolver($context), $store)->evaluate($flag);

        self::assertSame(EvaluationReason::Split, $evaluate($in)->reason);
        self::assertFalse($evaluate($out)->enabled);

        $store->flags['new-checkout'] = false;
        self::assertSame(EvaluationReason::Disabled, $evaluate($in)->reason);
        self::assertFalse($evaluate($in)->enabled);

        $store->flags['new-checkout'] = true;
        self::assertTrue($evaluate($in)->enabled);
        self::assertSame(EvaluationReason::Split, $evaluate($in)->reason);
        self::assertFalse($evaluate($out)->enabled, 'reactivation restores the rollout, not a flag on for everyone');
        self::assertSame(EvaluationReason::Default, $evaluate($out)->reason);
    }

    public function testTheStoreCanDeactivateAFlagTheConfigurationDeclaresActive(): void
    {
        $evaluator = new FlagEvaluator([$this->provider(['f' => true])], $this->resolver(), new InMemoryFlagStore(['f' => false]));

        self::assertSame(EvaluationReason::Disabled, $evaluator->evaluate(FlagName::of('f'))->reason);
    }

    public function testAFlagTheStoreDoesNotHoldKeepsItsProviderState(): void
    {
        $evaluator = new FlagEvaluator([$this->provider(['f' => true])], $this->resolver(), new InMemoryFlagStore([]));

        self::assertTrue($evaluator->isEnabled(FlagName::of('f')));
    }

    /**
     * A state alone declares no rule: the store cannot bring into being a flag no provider declares.
     */
    public function testAStateForAFlagNoProviderKnowsIsIgnored(): void
    {
        $evaluator = new FlagEvaluator([$this->provider([])], $this->resolver(), new InMemoryFlagStore(['f' => true]));
        $evaluation = $evaluator->evaluate(FlagName::of('f'));

        self::assertFalse($evaluation->enabled);
        self::assertSame(EvaluationErrorCode::FlagNotFound, $evaluation->errorCode);
    }

    /**
     * @param array<string, bool> $flags
     */
    private function provider(array $flags): CountingProvider
    {
        $provider = new CountingProvider();
        $provider->flags = $flags;

        return $provider;
    }

    private function resolver(?EvaluationContext $context = null): CountingResolver
    {
        return new CountingResolver($context ?? EvaluationContext::anonymous());
    }
}
