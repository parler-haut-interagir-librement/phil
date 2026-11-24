<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application\Contract;

/**
 * Whether the carrier of a delivery method asks for the recipient's telephone number.
 *
 * `DeliveryAddresses` asks it before it keeps an address: a method whose carrier asks for a number refuses an
 * address without one, and any other method keeps none, even when given. The bundle's own answer reads the option
 * `delivery_addresses.phone_required_methods`; an application whose carriers say it otherwise declares a service
 * implementing this.
 */
interface PhoneRequirement
{
    public function requiresPhone(string $method): bool;
}
