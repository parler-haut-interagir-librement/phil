<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Domain\ValueObject;

use DateInterval;
use DateTimeImmutable;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\Status\EntryPoint;

/**
 * An access group of the site: its name, the entry points through which a member comes in, and how long a membership
 * lasts before it ends on its own, or null for as long as nobody removes it.
 */
final readonly class AccessGroup
{
    public const string KIND = 'group';

    /**
     * @param non-empty-list<EntryPoint> $entryPoints
     */
    private function __construct(
        public string $name,
        public array $entryPoints,
        public ?DateInterval $duration,
    ) {
    }

    /**
     * @param list<EntryPoint> $entryPoints
     */
    public static function of(string $name, array $entryPoints, ?DateInterval $duration = null): self
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name)) {
            throw InvalidAccess::because(\sprintf('An access group is 1 to 64 lowercase letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $name));
        }

        if ([] === $entryPoints) {
            throw InvalidAccess::because(\sprintf('The access group "%s" declares at least one entry point.', $name));
        }

        if ($duration instanceof DateInterval && new DateTimeImmutable('@0')->add($duration)->getTimestamp() < 1) {
            throw InvalidAccess::because(\sprintf('The membership of "%s" lasts at least a second.', $name));
        }

        $unique = [];

        foreach ($entryPoints as $entryPoint) {
            $unique[$entryPoint->value] = $entryPoint;
        }

        return new self($name, array_values($unique), $duration);
    }

    public function accepts(EntryPoint $entryPoint): bool
    {
        return \in_array($entryPoint, $this->entryPoints, true);
    }
}
