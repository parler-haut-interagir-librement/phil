<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\Receipt\DocumentKind;

/**
 * Gives the next number of a series, one series per kind of document, **sequential and without gaps**: a number
 * given is used, or the transaction that took it is rolled back with the document.
 *
 * `Infrastructure\Doctrine\DoctrineDocumentNumbering` is the Doctrine adapter; it requires a transaction.
 */
interface DocumentNumbering
{
    public function next(DocumentKind $kind): string;
}
