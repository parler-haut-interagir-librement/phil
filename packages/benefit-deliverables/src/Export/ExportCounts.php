<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Export;

/**
 * The counts of an export being written, which the rows add to as they go.
 *
 * @internal
 */
final class ExportCounts
{
    public int $rows = 0;

    public int $withoutAddress = 0;
}
