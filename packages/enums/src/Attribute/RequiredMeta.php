<?php

declare(strict_types=1);

namespace Phil\Enums\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * The meta properties every case of the enum must carry, such as a `Label` for each case a form shows. The PHPStan
 * extension reports a case without one; `Reflection::missingRequiredMeta()` lists them at run time, for a test.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class RequiredMeta
{
    /** @var list<class-string<AbstractMetaProperty<*>>> */
    public array $metaProperties;

    /**
     * @param class-string<AbstractMetaProperty<*>> ...$metaProperties
     */
    public function __construct(string ...$metaProperties)
    {
        $this->metaProperties = array_values($metaProperties);
    }
}
