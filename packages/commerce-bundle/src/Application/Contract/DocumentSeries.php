<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Contract;

use Phil\CommerceBundle\Domain\ValueObject\DocumentType;

/**
 * Gives the next number of a type's series, **sequential and without gaps**, inside the transaction that stores the
 * document. `Infrastructure\Doctrine\DoctrineDocumentNumbering` is the Doctrine adapter.
 */
interface DocumentSeries
{
    public function nextIn(DocumentType $type): string;
}
