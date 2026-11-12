<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Domain\Exception;

use RuntimeException;

/**
 * A medium that is not deleted, because a record still shows it and its column refuses to lose it.
 */
final class MediaInUse extends RuntimeException implements MediaException
{
    /**
     * @param list<string> $places where it is used: `App\Entity\Post.cover (3)`
     */
    private function __construct(
        string $message,
        public readonly array $places,
    ) {
        parent::__construct($message);
    }

    /**
     * @param list<string> $places
     */
    public static function by(string $id, array $places): self
    {
        return new self(\sprintf('The medium %s is still used by %s.', $id, implode(', ', $places)), $places);
    }
}
