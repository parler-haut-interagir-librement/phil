<?php

declare(strict_types=1);

namespace Phil\Enums\Attribute;

use Attribute;
use InvalidArgumentException;
use Override;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * How often `random()` draws a case, relative to the others: a case of weight 3 comes up three times as often as a
 * case of weight 1, a case of weight 0 never. A case without the attribute weighs 1.
 *
 * @extends AbstractMetaProperty<int>
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final class Weight extends AbstractMetaProperty
{
    #[Override]
    public static function defaultValue(): int
    {
        return 1;
    }

    #[Override]
    protected function transform(mixed $value): mixed
    {
        if ($value < 0) {
            throw new InvalidArgumentException(\sprintf('A weight is 0 or more, %d given.', $value));
        }

        return $value;
    }
}
