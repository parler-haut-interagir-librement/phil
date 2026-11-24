<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\ValueObject;

use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;

/**
 * Two different members, in byte order, so that a mutual relationship is kept once whoever asked for it.
 */
final readonly class MemberPair
{
    private function __construct(
        public string $first,
        public string $second,
    ) {
    }

    /**
     * @throws InvalidFollow when a member is not an identifier, or both are the same
     */
    public static function of(string $one, string $other): self
    {
        Identifier::id($one, 'member');
        Identifier::id($other, 'member');

        if ($one === $other) {
            throw InvalidFollow::because('A mutual relationship joins two different members.');
        }

        return strcmp($one, $other) < 0 ? new self($one, $other) : new self($other, $one);
    }

    public function other(string $member): string
    {
        return $member === $this->first ? $this->second : $this->first;
    }
}
