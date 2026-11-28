<?php

declare(strict_types=1);

namespace Phil\ContentAccess\Condition;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\ContentAccess\AccessFacts;
use Phil\ContentAccess\Entitlement\Grant;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `access.feature`: the viewer holds an active grant of the feature. With `after_days`, the content opens only that
 * many days after the grant started — an opening by seniority —; with `for_days`, it closes that many days after the
 * grant started — a rolling duration of access. A fixed end of access is a `date.compare` on the fact `now`.
 *
 * When the content is not open yet, the verdict says when it opens, so that a locked entry can show what remains.
 */
final readonly class FeatureCondition implements ConditionType
{
    public const int MAX_DAYS = 36500;

    public function name(): string
    {
        return 'access.feature';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(
            Operand::identifier('feature'),
            Operand::integer('after_days', required: false, min: 0, max: self::MAX_DAYS),
            Operand::integer('for_days', required: false, min: 1, max: self::MAX_DAYS),
        );
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $feature = $operands->string('feature');
        $now = $facts->has(AccessFacts::NOW) ? $facts->get(AccessFacts::NOW) : null;
        $grants = $facts->has(AccessFacts::VIEWER_GRANTS) ? $facts->get(AccessFacts::VIEWER_GRANTS) : [];

        if (!$now instanceof DateTimeImmutable) {
            return Verdict::fail('fact.type', ['fact' => AccessFacts::NOW, 'expected' => 'date']);
        }

        if (!\is_array($grants)) {
            return Verdict::fail('fact.type', ['fact' => AccessFacts::VIEWER_GRANTS, 'expected' => 'list']);
        }

        $afterDays = $operands->has('after_days') ? $operands->int('after_days') : 0;
        $forDays = $operands->has('for_days') ? $operands->int('for_days') : null;
        $opensAt = null;
        $elapsed = false;

        foreach ($grants as $grant) {
            if (!$grant instanceof Grant || $grant->feature !== $feature || !$grant->activeAt($now)) {
                continue;
            }

            $opens = $grant->daysAfterStart($afterDays);

            if ($now < $opens) {
                $opensAt = !$opensAt instanceof DateTimeImmutable || $opens < $opensAt ? $opens : $opensAt;

                continue;
            }

            if (null !== $forDays && $grant->daysAfterStart($forDays) <= $now) {
                $elapsed = true;

                continue;
            }

            return Verdict::pass('access.feature-held', ['feature' => $feature]);
        }

        if ($opensAt instanceof DateTimeImmutable) {
            return Verdict::fail('access.feature-not-yet', ['feature' => $feature, 'opens_at' => $opensAt->format(DateTimeInterface::ATOM)]);
        }

        return Verdict::fail($elapsed ? 'access.feature-elapsed' : 'access.feature-missing', ['feature' => $feature]);
    }
}
