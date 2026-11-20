<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\ValueObject;

use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;

/**
 * Where a unit stands in the tree of its organization: the identifiers of its ancestors, from the root, then its own,
 * written `/root/child/unit/` as a store keeps it. At most sixteen levels.
 *
 * The descendants of a unit are the paths that start with its own, and these sort between its path and that path with
 * its last `/` turned into `0`: a store finds them by one range of an index, without a pattern.
 */
final readonly class UnitPath
{
    public const int MAX_DEPTH = 16;

    /** Sixteen identifiers, each followed by a `/`, after the first `/`. */
    public const int MAX_LENGTH = self::MAX_DEPTH * (Identifier::MAX_LENGTH + 1) + 1;

    private const string SEPARATOR = '/';

    /**
     * @param non-empty-list<non-empty-string> $ids
     */
    private function __construct(
        public array $ids,
    ) {
    }

    public static function root(UnitId $id): self
    {
        return new self([$id->value]);
    }

    /**
     * @throws InvalidOrganization when the path would be deeper than sixteen levels, or already holds the unit
     */
    public function child(UnitId $id): self
    {
        if (\in_array($id->value, $this->ids, true)) {
            throw InvalidOrganization::because(\sprintf('The unit "%s" is already on this path.', $id->value));
        }

        if (\count($this->ids) >= self::MAX_DEPTH) {
            throw InvalidOrganization::because(\sprintf('A unit is at most %d levels deep.', self::MAX_DEPTH));
        }

        return new self([...$this->ids, $id->value]);
    }

    /**
     * The path `toString()` wrote.
     *
     * @throws InvalidOrganization when the text is not a path
     */
    public static function fromString(string $path): self
    {
        if (!str_starts_with($path, self::SEPARATOR) || !str_ends_with($path, self::SEPARATOR) || \strlen($path) < 3) {
            throw InvalidOrganization::because(\sprintf('A unit path is written "/root/unit/"; "%s" is not.', $path));
        }

        $path = substr($path, 1, -1);
        $ids = explode(self::SEPARATOR, $path);

        if (\count($ids) > self::MAX_DEPTH) {
            throw InvalidOrganization::because(\sprintf('A unit is at most %d levels deep.', self::MAX_DEPTH));
        }

        $checked = array_map(static fn (string $id): string => UnitId::fromString($id)->value, $ids);

        if (\count(array_unique($checked)) !== \count($checked)) {
            throw InvalidOrganization::because('A unit path holds each unit once.');
        }

        return new self($checked);
    }

    public function toString(): string
    {
        return self::SEPARATOR.implode(self::SEPARATOR, $this->ids).self::SEPARATOR;
    }

    /**
     * The bound every descendant's path sorts below, byte by byte: the path with its last `/` turned into `0`.
     */
    public function upperBound(): string
    {
        return substr($this->toString(), 0, -1).'0';
    }

    public function unitId(): UnitId
    {
        return UnitId::fromString(array_last($this->ids));
    }

    public function parentId(): ?UnitId
    {
        $count = \count($this->ids);

        return $count > 1 ? UnitId::fromString($this->ids[$count - 2]) : null;
    }

    /**
     * @return list<UnitId> the ancestors, from the root, without the unit
     */
    public function ancestorIds(): array
    {
        return array_map(UnitId::fromString(...), \array_slice($this->ids, 0, -1));
    }

    /**
     * @return non-empty-list<UnitId> the ancestors from the root, then the unit
     */
    public function unitIds(): array
    {
        return array_map(UnitId::fromString(...), $this->ids);
    }

    /**
     * The level of the unit, a root being at 1.
     *
     * @return int<1, max>
     */
    public function depth(): int
    {
        return \count($this->ids);
    }

    public function contains(UnitId $id): bool
    {
        return \in_array($id->value, $this->ids, true);
    }

    /**
     * Whether this path is the other's or one of its descendants'.
     */
    public function isWithin(self $other): bool
    {
        return str_starts_with($this->toString(), $other->toString());
    }

    /**
     * This path once the subtree of `$from` moved to `$to`.
     *
     * @throws InvalidOrganization when this path is not within `$from`, or would be deeper than sixteen levels
     */
    public function rebase(self $from, self $to): self
    {
        if (!$this->isWithin($from)) {
            throw InvalidOrganization::because(\sprintf('The path "%s" is not within "%s".', $this->toString(), $from->toString()));
        }

        return self::fromString($to->toString().substr($this->toString(), \strlen($from->toString())));
    }

    public function equals(self $other): bool
    {
        return $this->ids === $other->ids;
    }
}
