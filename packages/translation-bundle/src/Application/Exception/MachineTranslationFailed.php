<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application\Exception;

use Phil\TranslationBundle\Domain\Exception\TranslationBundleException;
use RuntimeException;
use Throwable;

/**
 * Machine translation asked for while it is off, or a translator that did not answer one target per source.
 */
final class MachineTranslationFailed extends RuntimeException implements TranslationBundleException
{
    public static function off(): self
    {
        return new self('Machine translation is off: set phil_translation.machine_translation.translator to turn it on.');
    }

    public static function answer(string $translator, int $expected, int $given): self
    {
        return new self(\sprintf('The machine translator "%s" gave %d targets for %d sources.', $translator, $given, $expected));
    }

    public static function because(string $translator, Throwable $previous): self
    {
        return new self(\sprintf('The machine translator "%s" failed: %s', $translator, $previous->getMessage()), 0, $previous);
    }
}
