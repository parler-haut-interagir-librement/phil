<?php

declare(strict_types=1);

namespace Phil\Enums\Traits;

use Phil\Enums\Collection\CaseCollection;
use Phil\Enums\Meta\Reflection;
use UnitEnum;

/**
 * The enum as a state machine, from the `#[TransitionsTo]` of its cases: where a case may move, which cases are
 * final, which are initial. For a lifecycle that needs guards, events or history, Symfony Workflow reads the same
 * transitions; this trait answers the questions a template or a validator asks.
 */
trait AsStatefulEnumTrait
{
    /**
     * Whether the case may move to the other one.
     */
    public function canTransitionTo(self $to): bool
    {
        return \in_array($to, Reflection::transitions($this), true);
    }

    /**
     * The cases this one may move to, in declaration order.
     *
     * @return CaseCollection<self>
     */
    public function nextStates(): CaseCollection
    {
        return CaseCollection::of(self::cases())->filter($this->canTransitionTo(...));
    }

    /**
     * Whether the case moves nowhere.
     */
    public function isFinal(): bool
    {
        return [] === Reflection::transitions($this);
    }

    /**
     * The cases no case moves to, in declaration order: where a lifecycle starts.
     *
     * @return CaseCollection<self>
     */
    public static function initialStates(): CaseCollection
    {
        $reached = array_merge(...array_map(Reflection::transitions(...), self::cases()));

        return CaseCollection::of(self::cases())->filter(static fn (UnitEnum $case): bool => !\in_array($case, $reached, true));
    }

    /**
     * Every transition of the enum, as pairs of cases, in declaration order of their origins.
     *
     * @return list<array{self, self}>
     */
    public static function transitions(): array
    {
        $pairs = [];

        foreach (self::cases() as $from) {
            foreach ($from->nextStates() as $to) {
                $pairs[] = [$from, $to];
            }
        }

        return $pairs;
    }
}
