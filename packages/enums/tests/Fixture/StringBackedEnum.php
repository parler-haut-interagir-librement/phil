<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Phil\Enums\Traits\AsInvocableEnumTrait;
use Phil\Enums\Traits\AsSelfAwareableEnumTrait;
use Phil\Enums\Traits\AsStringSelectableEnumTrait;

enum StringBackedEnum: string
{
    use AsInvocableEnumTrait;
    use AsSelfAwareableEnumTrait;
    use AsStringSelectableEnumTrait;

    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}
