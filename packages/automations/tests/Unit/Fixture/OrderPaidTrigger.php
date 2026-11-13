<?php

declare(strict_types=1);

namespace Phil\Automations\Tests\Unit\Fixture;

use Override;
use Phil\Automations\Catalogue\ComponentDescription;
use Phil\Automations\Catalogue\OptionSpec;
use Phil\Automations\Catalogue\OptionType;
use Phil\Automations\Contract\Trigger;
use Phil\Automations\Definition\Options;
use Phil\Automations\Engine\Payload;
use Phil\Automations\Engine\TriggerMatch;

/**
 * Matches an order paid, of a plan when the options name one.
 */
final readonly class OrderPaidTrigger implements Trigger
{
    #[Override]
    public function describe(): ComponentDescription
    {
        return new ComponentDescription('shop.order_paid', 'Order paid', [new OptionSpec('plan', OptionType::Choice, false, 'Plan', ['basic', 'pro'])]);
    }

    #[Override]
    public function events(): array
    {
        return [OrderPaid::class];
    }

    #[Override]
    public function match(object $event, Options $options): ?TriggerMatch
    {
        if (!$event instanceof OrderPaid || (null !== $options->string('plan') && $options->string('plan') !== $event->plan)) {
            return null;
        }

        return new TriggerMatch('order:'.$event->order, $event->customer, Payload::of(['order' => $event->order, 'plan' => $event->plan]));
    }
}
