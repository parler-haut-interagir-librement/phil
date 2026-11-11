<?php

declare(strict_types=1);

namespace Phil\Enums\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * Another spelling `parse()` accepts for a case: a value the enum used to have, the name an older API sent. A case
 * may carry several; an alias that looks like a number is written as a string, `#[Alias('3')]`.
 *
 * @extends AbstractMetaProperty<string>
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT | Attribute::IS_REPEATABLE)]
final class Alias extends AbstractMetaProperty
{
}
