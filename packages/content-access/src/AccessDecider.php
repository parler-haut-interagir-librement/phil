<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

use DateTimeImmutable;
use Phil\ContentAccess\Entitlement\Entitlements;
use Phil\ContentAccess\Exception\ReservedFact;
use Phil\Rules\Evaluation\Step;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Rule;
use Psr\Clock\ClockInterface;

/**
 * Decides what a viewer may see of a content under its policy, at the clock's instant.
 *
 * A public content, or one past its early access, is read in full by everyone without reading the viewer; a content for
 * members asks only whether the viewer is signed in; a restricted content evaluates its rule on the viewer's facts,
 * whose grants are read only when a condition asks for them. A viewer without the right gets the preview, the locked
 * entry or nothing, as the policy says.
 *
 * Keeps nothing from one decision to the next: one instance serves every request of a worker.
 */
final readonly class AccessDecider
{
    public function __construct(
        private Entitlements $entitlements,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $facts the application's own facts for the rule, a value or a closure each
     *
     * @throws ReservedFact  when a fact takes a name the library sets
     * @throws LimitExceeded when the rule needs more evaluations than its limits allow
     */
    public function decide(AccessPolicy $policy, Viewer $viewer, array $facts = []): Decision
    {
        $now = $this->clock->now();

        if (Audience::Public === $policy->audience) {
            return new Decision(Access::Full, Reason::Public, false);
        }

        if ($policy->isPublicAt($now)) {
            return new Decision(Access::Full, Reason::EarlyAccessEnded, false);
        }

        $changesAt = $policy->publicFrom;
        $rule = $policy->rule;

        // Past the public cases, a policy without rule is one for members.
        if (!$rule instanceof Rule) {
            return $viewer->isSignedIn()
                ? new Decision(Access::Full, Reason::Member, true, $changesAt)
                : $this->denied($policy, Reason::Anonymous, $changesAt, []);
        }

        $result = $rule->evaluate(AccessFacts::for($viewer, $this->entitlements, $now, $facts));

        return $result->passed
            ? new Decision(Access::Full, Reason::RulePassed, true, $changesAt, $result->explanation())
            : $this->denied($policy, Reason::RuleFailed, $changesAt, $result->explanation());
    }

    /**
     * @param list<Step> $explanation
     */
    private function denied(AccessPolicy $policy, Reason $reason, ?DateTimeImmutable $changesAt, array $explanation): Decision
    {
        if (WhenDenied::Hidden === $policy->whenDenied) {
            return new Decision(Access::Hidden, $reason, true, $changesAt);
        }

        return new Decision($policy->preview ? Access::Preview : Access::Locked, $reason, true, $changesAt, $explanation);
    }
}
