<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Plan;

/**
 * What the import does with a row: create a record, update the record of the same natural key, or refuse the row.
 */
enum RowAction: string
{
    case Create = 'create';
    case Update = 'update';
    case Refuse = 'refuse';
}
