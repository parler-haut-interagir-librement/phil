<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Contract;

use Phil\CommerceBundle\Domain\ValueObject\DocumentType;

/**
 * Declares document types to the register: the core declares its receipts and credit notes, a module its own — a
 * packing slip, a return slip. A service implementing it is tagged `phil_commerce.document_types` by
 * autoconfiguration.
 */
interface DocumentTypes
{
    /**
     * @return iterable<DocumentType>
     */
    public function documentTypes(): iterable;
}
