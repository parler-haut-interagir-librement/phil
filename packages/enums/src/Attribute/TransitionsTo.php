<?php

declare(strict_types=1);

namespace Phil\Enums\Attribute;

use Attribute;
use UnitEnum;

/**
 * The cases this case may move to: a state machine declared on the enum, read by `canTransitionTo()` and
 * `nextStates()`. A case without the attribute is final.
 *
 * ```php
 * #[TransitionsTo(self::Paid, self::Cancelled)]
 * case Pending = 'pending';
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class TransitionsTo
{
    /** @var list<UnitEnum> */
    public array $cases;

    public function __construct(UnitEnum ...$cases)
    {
        $this->cases = array_values($cases);
    }
}
