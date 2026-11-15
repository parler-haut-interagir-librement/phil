<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Contract\DocumentTypes;
use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\Receipt\DocumentKind;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;

/**
 * The core's own document types, receipts and credit notes, listed among the buyer's documents with the prefixes and
 * digits of `phil_commerce.receipts`, and those the application declares under `phil_commerce.document_types`, hidden
 * unless their `visibility` says otherwise.
 */
final readonly class CoreDocumentTypes implements DocumentTypes
{
    /**
     * @param array<string, array{prefix: string, digits: int, visibility?: string}> $declared code → prefix, digits and
     *                                                                                         visibility
     */
    public function __construct(
        private string $receiptPrefix = 'R-',
        private string $creditNotePrefix = 'CN-',
        private int $digits = 8,
        private array $declared = [],
    ) {
    }

    #[Override]
    public function documentTypes(): iterable
    {
        yield DocumentType::of(DocumentKind::Receipt->value, $this->receiptPrefix, $this->digits, Visibility::Listed);
        yield DocumentType::of(DocumentKind::CreditNote->value, $this->creditNotePrefix, $this->digits, Visibility::Listed);

        foreach ($this->declared as $code => $type) {
            yield DocumentType::of($code, $type['prefix'], $type['digits'], Visibility::from($type['visibility'] ?? Visibility::Hidden->value));
        }
    }
}
