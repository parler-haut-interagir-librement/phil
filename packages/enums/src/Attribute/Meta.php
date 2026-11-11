<?php

declare(strict_types=1);

namespace Phil\Enums\Attribute;

use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/**
 * The meta properties an enum — or a trait it uses — enables, in the order their accessors are looked up:
 * `#[Meta(Label::class, Description::class)]`. A case attribute whose class is not listed here is not read.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Meta
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
