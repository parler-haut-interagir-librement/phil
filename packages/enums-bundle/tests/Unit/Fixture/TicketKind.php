<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Fixture;

use Phil\EnumsBundle\Application\Translation\Translatable;
use Phil\EnumsBundle\Infrastructure\Translation\AsTranslatableEnumTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * A translatable enum with its own domain and prefix, and no label: its keys are generated.
 */
#[Translatable(domain: 'support', prefix: 'ticket')]
enum TicketKind: int implements TranslatableInterface
{
    use AsTranslatableEnumTrait;

    case BugReport = 1;
    case FEATURE_REQUEST = 2;
}
