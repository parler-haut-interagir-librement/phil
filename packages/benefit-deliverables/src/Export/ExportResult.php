<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Export;

use SensitiveParameter;

/**
 * A shipping export just written: its record, and the signed token of its download link, valid until the record
 * expires.
 */
final readonly class ExportResult
{
    public function __construct(
        public ExportRecord $record,
        #[SensitiveParameter]
        public string $token,
    ) {
    }
}
