<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Infrastructure\Routing;

use ErrorException;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagNotRoutable;
use Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagRoutingCondition;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CountingResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A route condition reads only the flags whose value does not depend on who asks: the router runs before the
 * firewall.
 */
#[CoversClass(FlagRoutingCondition::class)]
#[CoversClass(FlagNotRoutable::class)]
#[CoversClass(FlagDefinition::class)]
final class FlagRoutingConditionTest extends TestCase
{
    public function testAFlagOnOrOffForEveryoneIsRead(): void
    {
        $condition = $this->condition();

        self::assertTrue($condition->enabled('everyone'));
        self::assertFalse($condition->enabled('nobody'));
        self::assertFalse($condition->enabled('unknown'));
    }

    /**
     * Read without a warning: a flag without an experiment, or unknown, has no variant to name.
     */
    public function testAFlagWithoutAnExperimentHasNoVariant(): void
    {
        $condition = $this->condition();
        set_error_handler(static function (int $level, string $message): never {
            throw new ErrorException($message, 0, $level);
        });

        try {
            self::assertSame([null, null], [$condition->variant('everyone'), $condition->variant('unknown')]);
        } finally {
            restore_error_handler();
        }
    }

    public function testAFlagTargetedAtProjectsIsRead(): void
    {
        self::assertTrue($this->condition(EvaluationContext::of(project: 'p1'))->enabled('by-project'));
        self::assertSame('a', $this->condition(EvaluationContext::of(project: 'p1'))->variant('split-by-project'));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function targetedFlags(): iterable
    {
        yield 'members by subject' => ['by-subject', false];
        yield 'rollout by tenant' => ['by-tenant', false];
        yield 'experiment by subject' => ['split-by-subject', true];
        yield 'experiment by subject, read as a switch' => ['split-by-subject', false];
    }

    #[DataProvider('targetedFlags')]
    public function testAFlagTargetedAtSubjectsOrTenantsIsRefused(string $flag, bool $asVariant): void
    {
        $condition = $this->condition(EvaluationContext::of(tenant: 't1', subject: 's1'));

        $this->expectException(FlagNotRoutable::class);
        $this->expectExceptionMessageIsOrContains("#[IsFeatureEnabled('".$flag."')]");

        if ($asVariant) {
            $condition->variant($flag);
        } else {
            $condition->enabled($flag);
        }
    }

    private function condition(?EvaluationContext $context = null): FlagRoutingCondition
    {
        return new FlagRoutingCondition(new FlagEvaluator(
            [ConfiguredFlagProvider::fromConfiguration([
                'everyone' => ['active' => true],
                'nobody' => ['active' => false],
                'by-project' => ['active' => true, 'rules' => [['attribute' => 'project', 'members' => ['p1']]]],
                'split-by-project' => ['active' => true, 'experiment' => ['attribute' => 'project', 'variants' => ['a' => 100, 'b' => 0]]],
                'by-subject' => ['active' => true, 'rules' => [['attribute' => 'project', 'members' => ['p1']], ['attribute' => 'subject', 'members' => ['s1']]]],
                'by-tenant' => ['active' => true, 'rules' => [['attribute' => 'tenant', 'percentage' => 50]]],
                'split-by-subject' => ['active' => true, 'experiment' => ['attribute' => 'subject', 'variants' => ['a' => 50, 'b' => 50]]],
            ])],
            new CountingResolver($context ?? EvaluationContext::anonymous()),
        ));
    }
}
