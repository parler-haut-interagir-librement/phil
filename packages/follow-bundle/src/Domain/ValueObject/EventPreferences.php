<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Domain\ValueObject;

use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Status\FollowLevel;

/**
 * The level a member chose for each kind of event of a subject they follow at the custom level: `watching`, `mentions`
 * or `ignoring` by event key. An event they did not name reaches them when it mentions them.
 */
final readonly class EventPreferences
{
    public const int MAX = 64;

    /** @var array<string, FollowLevel> */
    public array $levels;

    /**
     * @param array<string, FollowLevel> $levels
     *
     * @throws InvalidFollow when an event is not a key, a level is `custom`, or there are too many
     */
    public function __construct(array $levels = [])
    {
        if (\count($levels) > self::MAX) {
            throw InvalidFollow::because(\sprintf('A follow names %d events at most.', self::MAX));
        }

        foreach ($levels as $event => $level) {
            Identifier::key($event, 'event');

            if (FollowLevel::Custom === $level) {
                throw InvalidFollow::because('The level of one event is "watching", "mentions" or "ignoring".');
            }
        }

        ksort($levels);
        $this->levels = $levels;
    }

    /**
     * Reads what a form or a stored row holds: event keys and level names.
     *
     * @param array<array-key, mixed> $levels
     *
     * @throws InvalidFollow when an entry is not an event key with a level name
     */
    public static function fromArray(array $levels): self
    {
        $read = [];

        foreach ($levels as $event => $level) {
            if (!\is_string($event) || !\is_string($level)) {
                throw InvalidFollow::because('The preferences of a follow are event keys with level names.');
            }

            $read[$event] = FollowLevel::tryFrom($level) ?? throw InvalidFollow::because('The level of one event is "watching", "mentions" or "ignoring".');
        }

        return new self($read);
    }

    public function levelFor(string $event): FollowLevel
    {
        return $this->levels[$event] ?? FollowLevel::Mentions;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_map(static fn (FollowLevel $level): string => $level->value, $this->levels);
    }
}
