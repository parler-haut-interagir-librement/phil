<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Fixtures;

use Phil\EmbedBundle\Domain\ValueObject\SigningKey;

/**
 * The keys of the tests: 2026-11 signs, 2026-05 is the previous one.
 */
final class Keys
{
    public const string CURRENT = 'a current secret of thirty-two bytes!';

    public const string PREVIOUS = 'a previous secret of thirty-two bytes';

    /**
     * @return list<SigningKey>
     */
    public static function both(): array
    {
        return [SigningKey::of('2026-11', self::CURRENT), SigningKey::of('2026-05', self::PREVIOUS)];
    }
}
