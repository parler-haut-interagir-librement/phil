<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Exception;

use OutOfBoundsException;

/**
 * A title, a copy or a loan that does not exist, or a copy that is not out.
 */
final class NotFound extends OutOfBoundsException
{
    public static function title(string $id): self
    {
        return new self(\sprintf('There is no title "%s".', $id));
    }

    public static function copy(string $reference): self
    {
        return new self(\sprintf('There is no copy "%s".', $reference));
    }

    public static function loan(string $id): self
    {
        return new self(\sprintf('There is no loan "%s".', $id));
    }

    public static function notOut(string $barcode): self
    {
        return new self(\sprintf('The copy "%s" is not out.', $barcode));
    }
}
