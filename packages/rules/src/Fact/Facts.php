<?php

declare(strict_types=1);

namespace Phil\Rules\Fact;

use Closure;
use Phil\Rules\Exception\MissingFact;

/**
 * Facts given as values, or as closures computed the first time a condition reads them and then kept for the rest of
 * the scope's life: a costly fact no condition reads is never computed.
 *
 *     Facts::of(['country' => 'CA', 'orders' => static fn (): int => $orders->countFor($customer)]);
 */
final class Facts implements FactSource
{
    /** @var array<string, mixed> */
    private array $computed = [];

    /**
     * @param array<string, mixed> $facts
     */
    private function __construct(private readonly array $facts)
    {
    }

    /**
     * @param array<string, mixed> $facts a value, or a closure without parameters that computes it
     */
    public static function of(array $facts): self
    {
        return new self($facts);
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->facts);
    }

    public function get(string $name): mixed
    {
        if (!\array_key_exists($name, $this->facts)) {
            throw MissingFact::named($name);
        }

        $fact = $this->facts[$name];

        if (!$fact instanceof Closure) {
            return $fact;
        }

        if (!\array_key_exists($name, $this->computed)) {
            $this->computed[$name] = $fact();
        }

        return $this->computed[$name];
    }
}
