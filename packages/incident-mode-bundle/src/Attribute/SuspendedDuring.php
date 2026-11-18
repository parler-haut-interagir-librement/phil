<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Attribute;

use Attribute;

/**
 * Refuses the controller while an incident suspends one of the restrictions named, for the member of the request —
 * an established member is spared from the restrictions the configuration lists. On a class, it covers every action;
 * with methods named, only the requests of those methods.
 *
 * ```php
 * #[SuspendedDuring('signups')]
 * public function register(Request $request): Response
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class SuspendedDuring
{
    /** @var list<string> */
    public array $restrictions;

    /** @var list<string> */
    public array $methods;

    /**
     * @param string|array<array-key, string> $restrictions the names of the restrictions, such as `signups` or `invitations`
     * @param array<array-key, string>        $methods      the HTTP methods refused, such as `POST`; every method when empty
     */
    public function __construct(string|array $restrictions, array $methods = [])
    {
        $this->restrictions = \is_string($restrictions) ? [$restrictions] : array_values($restrictions);
        $this->methods = array_values(array_map(strtoupper(...), $methods));
    }

    public function covers(string $method): bool
    {
        return [] === $this->methods || \in_array(strtoupper($method), $this->methods, true);
    }
}
