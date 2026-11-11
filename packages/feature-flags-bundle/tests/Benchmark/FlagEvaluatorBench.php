<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Benchmark;

use Generator;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Edit\RevisedDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\Bucket;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\Experiment;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\Percentage;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\MemberOf;
use Phil\FeatureFlagsBundle\Domain\Flag\Rule\PercentageRollout;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;
use Phil\FeatureFlagsBundle\Domain\Flag\Variant;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryDefinitions;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\SwitchableResolver;

/**
 * Controllers, route conditions and Twig ask for flags on every request: the first ask of each flag evaluates its
 * rules (a murmur3a bucket for a rollout or an experiment), every later one in the request reads the memo.
 */
final class FlagEvaluatorBench
{
    private readonly FlagEvaluator $evaluator;

    /**
     * @var list<FlagName>
     */
    private array $flags = [];

    private readonly FlagName $rollout;

    private readonly Experiment $experiment;

    private readonly EvaluationContext $context;

    public function __construct()
    {
        $definitions = new InMemoryDefinitions();
        $members = array_map(static fn (int $index): string => 'tenant-'.$index, range(1, 500));
        $experiment = Experiment::of(TargetingAttribute::Subject, [
            Variant::of(VariantName::of('control'), 34),
            Variant::of(VariantName::of('compact'), 33),
            Variant::of(VariantName::of('wide'), 33),
        ]);

        for ($index = 0; $index < 50; ++$index) {
            $name = FlagName::of(\sprintf('feature.flag-%02d', $index));
            $definitions->definitions[$name->value] = new RevisedDefinition(match ($index % 4) {
                0 => new FlagDefinition($name, true),
                1 => new FlagDefinition($name, true, [new PercentageRollout(TargetingAttribute::Subject, Percentage::of(20))]),
                2 => new FlagDefinition($name, true, [MemberOf::of(TargetingAttribute::Tenant, $members), new PercentageRollout(TargetingAttribute::Subject, Percentage::of(50))]),
                default => new FlagDefinition($name, true, experiment: $experiment),
            }, 1);
            $this->flags[] = $name;
        }

        $this->context = EvaluationContext::of('tenant-9999', 'shop', 'user-7f3a9c2e');
        $this->evaluator = new FlagEvaluator([$definitions], new SwitchableResolver($this->context));
        $this->rollout = $this->flags[1];
        $this->experiment = $experiment;
    }

    /**
     * Each flag asked once in a fresh request: every evaluation runs its rules.
     *
     * @ParamProviders("flagCounts")
     *
     * @param array{count: int} $params
     */
    public function benchFirstEvaluation(array $params): void
    {
        $this->evaluator->reset();

        for ($index = 0; $index < $params['count']; ++$index) {
            $this->evaluator->evaluate($this->flags[$index]);
        }
    }

    /**
     * The same flags asked again later in the request: every evaluation is a memo hit.
     *
     * @ParamProviders("flagCounts")
     *
     * @param array{count: int} $params
     */
    public function benchMemoizedEvaluation(array $params): void
    {
        for ($index = 0; $index < $params['count']; ++$index) {
            $this->evaluator->evaluate($this->flags[$index]);
        }
    }

    public function benchBucket(): void
    {
        Bucket::of($this->rollout, 'user-7f3a9c2e');
    }

    public function benchExperimentAssign(): void
    {
        $this->experiment->assign($this->rollout, $this->context);
    }

    /**
     * @return Generator<string, array{count: int}>
     */
    public function flagCounts(): Generator
    {
        yield '1 flag' => ['count' => 1];
        yield '10 flags' => ['count' => 10];
        yield '50 flags' => ['count' => 50];
    }
}
