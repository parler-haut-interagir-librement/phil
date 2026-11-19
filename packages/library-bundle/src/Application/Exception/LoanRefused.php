<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Exception;

use DomainException;

/**
 * A loan, a reservation or a renewal the library refuses, with the reason — a stable code an application translates —
 * and the numbers its message needs:
 *
 * - `withdrawn`: the copy is withdrawn;
 * - `taken`: another loan keeps the copy over the span;
 * - `too_far_ahead`: the reservation starts further ahead than allowed, `minutes`;
 * - `too_long`: the loan lasts longer than allowed, `minutes`;
 * - `overdue`: the member has a copy out past its due date;
 * - `limit`: the member holds as many loans as allowed, `max`;
 * - `renewals`: the loan was renewed as many times as allowed, `max`.
 */
final class LoanRefused extends DomainException
{
    /**
     * @param array<string, int> $parameters
     */
    public function __construct(
        public readonly string $reason,
        public readonly array $parameters,
    ) {
        parent::__construct(\sprintf('The loan is refused: %s.', $reason));
    }

    /**
     * @param array<string, int> $parameters
     */
    public static function because(string $reason, array $parameters = []): self
    {
        return new self($reason, $parameters);
    }
}
