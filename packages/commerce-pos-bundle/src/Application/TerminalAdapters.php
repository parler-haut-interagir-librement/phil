<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\CommerceBundle\Domain\Exception\InvalidNotification;
use Phil\CommercePosBundle\Application\Contract\PaymentTerminal;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;

/**
 * The payment terminal adapters the application registered, found by name, or by the notification a processor sent.
 */
final readonly class TerminalAdapters
{
    /**
     * @param iterable<PaymentTerminal> $adapters
     */
    public function __construct(
        private iterable $adapters = [],
    ) {
    }

    public function named(string $name): PaymentTerminal
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->name() === $name) {
                return $adapter;
            }
        }

        throw PosRefused::noTerminalAdapter($name);
    }

    /**
     * The first adapter that recognises the notification; it still verifies its signature when it reads it.
     *
     * @param array<string, list<string>> $headers
     */
    public function recognising(array $headers): PaymentTerminal
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->recognises($headers)) {
                return $adapter;
            }
        }

        throw InvalidNotification::because('No payment terminal adapter recognises the notification.');
    }
}
