<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use Override;
use Phil\CommerceSubscriptionBundle\Application\Contract\PhoneRequirement;

/**
 * The delivery methods whose carrier asks for a telephone number, as configured: every other method keeps none.
 */
final readonly class ConfiguredPhoneRequirement implements PhoneRequirement
{
    /** @var array<string, true> */
    private array $methods;

    /**
     * @param list<string> $methods the codes of the delivery methods whose carrier asks for a telephone number
     */
    public function __construct(array $methods = [])
    {
        $this->methods = array_fill_keys($methods, true);
    }

    #[Override]
    public function requiresPhone(string $method): bool
    {
        return isset($this->methods[$method]);
    }
}
