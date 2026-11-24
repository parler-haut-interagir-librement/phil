<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application\Contract;

/**
 * Gives the next number of the consignors' statements: a series of its own, sequential, never shared with another
 * document. `Infrastructure\Doctrine\DoctrineStatementNumbering` counts in the statements' table; an application with a
 * series of its own — per year, per shop — aliases this interface to its service, which takes the number inside the
 * transaction that keeps the statement.
 */
interface StatementNumbering
{
    /**
     * @return string 1 to 32 visible ASCII characters
     */
    public function next(): string;
}
