<?php

declare(strict_types=1);

namespace Phil\Rules\Tests\Benchmark;

use DateTimeImmutable;
use Phil\Rules\Fact\Facts;
use Phil\Rules\Json\RuleReader;
use Phil\Rules\Rule;
use Phil\Rules\Type\ConditionTypes;

/**
 * The two hot paths: reading a stored rule of some seventy conditions, and evaluating it for one subject, as a segment
 * or a price book does once per person or per line.
 */
final readonly class RuleBench
{
    private string $json;

    private RuleReader $reader;

    private Rule $rule;

    private Facts $facts;

    public function __construct()
    {
        $branches = [];

        for ($i = 0; $i < 12; ++$i) {
            $branches[] = ['all' => [
                ['type' => 'fact.in', 'operands' => ['fact' => 'country', 'values' => ['CA', 'FR', 'BE', 'CH']]],
                ['type' => 'fact.compare', 'operands' => ['fact' => 'orders', 'operator' => 'gte', 'value' => $i]],
                ['not' => ['type' => 'fact.contains', 'operands' => ['fact' => 'groups', 'value' => 'group-'.$i]]],
                ['type' => 'date.compare', 'operands' => ['fact' => 'joined', 'operator' => 'before', 'date' => '2026-01-01']],
            ]];
        }

        $this->json = json_encode(['version' => 1, 'condition' => ['any' => $branches]], \JSON_THROW_ON_ERROR);
        $this->reader = new RuleReader(ConditionTypes::builtIn());
        $this->rule = $this->reader->fromJson($this->json);
        $this->facts = Facts::of(['country' => 'CA', 'orders' => 3, 'groups' => ['group-0', 'group-1', 'group-2', 'group-3'], 'joined' => new DateTimeImmutable('2027-03-01')]);
    }

    public function benchReadAStoredRule(): int
    {
        return \count($this->reader->fromJson($this->json)->typeNames());
    }

    public function benchEvaluateForOneSubject(): bool
    {
        return $this->rule->evaluate($this->facts)->passed;
    }
}
