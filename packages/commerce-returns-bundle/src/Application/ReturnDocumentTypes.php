<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Contract\DocumentTypes;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;

/**
 * The module's document type in the core's register: the return slip, numbered in its own series with the prefix
 * and digits of `phil_commerce_returns.slips`.
 */
final readonly class ReturnDocumentTypes implements DocumentTypes
{
    public const string RETURN_SLIP = 'return_slip';

    public function __construct(
        private string $prefix = 'RS-',
        private int $digits = 8,
    ) {
    }

    #[Override]
    public function documentTypes(): iterable
    {
        yield DocumentType::of(self::RETURN_SLIP, $this->prefix, $this->digits);
    }
}
