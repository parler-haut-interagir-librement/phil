<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\ValueObject;

use Phil\ModerationBundle\Domain\Exception\InvalidModeration;

/**
 * What is moderated: a type, which names the package's target — `forum.post`, `comment`, `profile` — and the
 * identifier of the content in it.
 */
final readonly class TargetReference
{
    public const string TYPE = '/^[a-z][a-z0-9_.-]{0,63}$/D';

    public const int MAX_ID_LENGTH = 190;

    private function __construct(
        public string $type,
        public string $id,
    ) {
    }

    public static function of(string $type, string $id): self
    {
        if (1 !== preg_match(self::TYPE, $type)) {
            throw InvalidModeration::because(\sprintf('"%s" is not a target type: a lowercase letter, then up to 63 lowercase letters, digits, dots, hyphens and underscores.', $type));
        }

        if ('' === $id || \strlen($id) > self::MAX_ID_LENGTH || 1 === preg_match('/[\x00-\x20\x7F]/', $id)) {
            throw InvalidModeration::because(\sprintf('A target is identified by 1 to %d visible characters.', self::MAX_ID_LENGTH));
        }

        return new self($type, $id);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
