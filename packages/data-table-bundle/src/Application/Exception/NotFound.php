<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Exception;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A table, an action, a column or a row that does not exist, or that is outside what this table shows.
 */
final class NotFound extends NotFoundHttpException implements DataTableException
{
    public static function table(string $name): self
    {
        return new self(\sprintf('There is no data table named "%s".', $name));
    }

    public static function action(string $table, string $name): self
    {
        return new self(\sprintf('The table "%s" has no action "%s" for these rows.', $table, $name));
    }

    public static function editableColumn(string $table, string $name): self
    {
        return new self(\sprintf('The table "%s" has no editable column "%s".', $table, $name));
    }

    public static function rows(string $table): self
    {
        return new self(\sprintf('The table "%s" does not hold every row asked for.', $table));
    }

    public static function export(string $table, string $format): self
    {
        return new self(\sprintf('The table "%s" offers no export as "%s".', $table, $format));
    }
}
