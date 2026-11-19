<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit\Fixture;

/**
 * The bytes of a one-pixel PNG image.
 */
final class Png
{
    public const string BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC';

    public static function bytes(): string
    {
        return base64_decode(self::BASE64, true);
    }
}
