<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Doctrine;

use BackedEnum;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use JsonException;
use Override;
use Phil\Enums\Collection\EnumSet;
use UnitEnum;

/**
 * A DBAL type that stores an `EnumSet` of one enum, as a bitmask or as a JSON list. The bundle registers one
 * instance per name its configuration declares, so that a column is mapped by that name:
 *
 * ```php
 * #[ORM\Column(type: 'permissions')]
 * private EnumSet $permissions;
 * ```
 *
 * `null` stays `null`, both ways. A stored value that names no case fails to convert rather than being dropped.
 */
final class EnumSetType extends Type
{
    /**
     * @param class-string<UnitEnum> $enum
     */
    public function __construct(
        private readonly string $enum,
        private readonly EnumSetStorage $storage,
    ) {
    }

    /**
     * @return class-string<UnitEnum>
     */
    public function enum(): string
    {
        return $this->enum;
    }

    public function storage(): EnumSetStorage
    {
        return $this->storage;
    }

    /**
     * @param array<string, mixed> $column
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return EnumSetStorage::Bitmask === $this->storage
            ? $platform->getIntegerTypeDeclarationSQL($column)
            : $platform->getJsonTypeDeclarationSQL($column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): int|string|null
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof EnumSet || $value->enum() !== $this->enum) {
            throw InvalidType::new($value, self::class, ['null', EnumSet::class.'<'.$this->enum.'>']);
        }

        return EnumSetStorage::Bitmask === $this->storage
            ? $value->toBitmask()
            : json_encode($value->values(), \JSON_THROW_ON_ERROR);
    }

    /**
     * @return EnumSet<UnitEnum>|null
     */
    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EnumSet
    {
        if (null === $value) {
            return null;
        }

        try {
            return EnumSetStorage::Bitmask === $this->storage ? $this->fromBitmask($value) : $this->fromJson($value);
        } catch (InvalidArgumentException|JsonException $exception) {
            throw ValueNotConvertible::new($value, self::class, $exception->getMessage(), $exception);
        }
    }

    /**
     * @return EnumSet<UnitEnum>
     */
    private function fromBitmask(mixed $value): EnumSet
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('A bitmask is an integer.');
        }

        return EnumSet::fromBitmask($this->enum, (int) $value);
    }

    /**
     * @return EnumSet<UnitEnum>
     */
    private function fromJson(mixed $value): EnumSet
    {
        $values = json_decode(\is_string($value) ? $value : '', true, 2, \JSON_THROW_ON_ERROR);

        if (!\is_array($values)) {
            throw new InvalidArgumentException('A set is stored as a JSON list.');
        }

        $options = array_column(array_map(static fn (UnitEnum $case): array => [$case, $case instanceof BackedEnum ? $case->value : $case->name], $this->enum::cases()), 0, 1);
        $cases = [];

        foreach ($values as $primitive) {
            $cases[] = (\is_int($primitive) || \is_string($primitive)) && isset($options[$primitive])
                ? $options[$primitive]
                : throw new InvalidArgumentException(\sprintf('%s is no value of enum %s.', json_encode($primitive, \JSON_THROW_ON_ERROR), $this->enum));
        }

        return EnumSet::of($this->enum, ...$cases);
    }
}
