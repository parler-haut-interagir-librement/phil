<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DddBundle\Domain\Identifier\Identifier;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use WeakMap;

/**
 * The DBAL type of an identifier: one concrete identifier class per column, stored in 16 bytes on every
 * platform.
 *
 * Each identifier gets its own subclass, which names the class it converts to, and the application registers
 * that subclass under a type name of its choosing (`doctrine.dbal.types` with DoctrineBundle):
 *
 * ```php
 * final class OrderIdType extends IdentifierType
 * {
 *     protected function identifierClass(): string
 *     {
 *         return OrderId::class;
 *     }
 * }
 * ```
 *
 * **One type per identifier class, not one type for all of them.** A shared type would read every column back
 * as the same class, and an `OrderId` would come out of a `customer_id` column without anything noticing. Here
 * the column decides the class, and a value of another identifier class is refused on the way in.
 *
 * **The column depends on the platform.** Where the platform has a native UUID type (`UUID` on PostgreSQL,
 * `UNIQUEIDENTIFIER` on SQL Server), the column is that type and the value travels as the canonical string.
 * Everywhere else (MySQL, MariaDB, SQLite), the platform's GUID declaration is a `CHAR(36)`: 36 characters that
 * compare as text. The column is then a fixed 16-byte binary instead (`BINARY(16)`, `BLOB` on SQLite), and the
 * value travels as the UUID's 16 raw bytes, which keep the time order of a UUIDv7 in a byte-wise index. A
 * platform is native when its GUID declaration is anything but that `CHAR(36)`, which is worked out once per
 * platform object, not for each value.
 *
 * **A value read back must be a UUIDv7**: `Identifier::fromString()` refuses anything else, and the refusal
 * surfaces as a DBAL conversion error rather than a domain error, since it says the column holds a value no
 * code of the application wrote. On a binary column, a value that is not exactly 16 bytes is refused the same
 * way.
 *
 * `doctrine/dbal` is an optional dependency of this bundle: nothing loads this class unless the application
 * registers a subclass of it, and an application that does already requires DBAL.
 *
 * @template T of Identifier
 */
abstract class IdentifierType extends Type
{
    /**
     * Whether each platform object has a native UUID type, worked out once per object: the conversions run for
     * every identifier column of every row, and the answer never changes for a platform. Weak, so that a platform
     * no longer used is not kept alive.
     *
     * @var WeakMap<AbstractPlatform, bool>|null
     */
    private static ?WeakMap $nativeUuid = null;

    /**
     * @return class-string<T>
     */
    abstract protected function identifierClass(): string;

    /**
     * @param array<string, mixed> $column
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($this->hasNativeUuid($platform)) {
            return $platform->getGuidTypeDeclarationSQL($column);
        }

        return $platform->getBinaryTypeDeclarationSQL(['length' => 16, 'fixed' => true]);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        $class = $this->identifierClass();

        // The exact class, as `Identifier::equals()` reads it: a value of another identifier class is the
        // mistake this type exists to catch, even when it carries a valid UUIDv7.
        if (!$value instanceof Identifier || $value::class !== $class) {
            throw InvalidType::new($value, static::class, ['null', $class]);
        }

        return $this->hasNativeUuid($platform) ? $value->toString() : $value->value->toBinary();
    }

    /**
     * @return T|null
     */
    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Identifier
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), static::class);
        }

        if ($this->hasNativeUuid($platform)) {
            return $this->fromCanonical($value);
        }

        try {
            $uuid = Uuid::fromBinary($value);
        } catch (InvalidArgumentException $invalidArgumentException) {
            // Raw bytes are not printable: the message carries their length rather than the value.
            throw ValueNotConvertible::new(\sprintf('%d bytes', \strlen($value)), static::class, null, $invalidArgumentException);
        }

        // Built from the UUID just read, not from its string printed and parsed again.
        try {
            return $this->identifierClass()::fromUuid($uuid);
        } catch (InvalidValue $invalidValue) {
            throw ValueNotConvertible::new($uuid->toRfc4122(), static::class, null, $invalidValue);
        }
    }

    /**
     * @return T
     */
    private function fromCanonical(string $canonical): Identifier
    {
        try {
            return $this->identifierClass()::fromString($canonical);
        } catch (InvalidValue $invalidValue) {
            throw ValueNotConvertible::new($canonical, static::class, null, $invalidValue);
        }
    }

    /**
     * A platform without a UUID type of its own falls back to a fixed `CHAR(36)` for its GUID declaration;
     * anything else is a native type.
     */
    private function hasNativeUuid(AbstractPlatform $platform): bool
    {
        self::$nativeUuid ??= new WeakMap();

        return self::$nativeUuid[$platform] ??= $platform->getGuidTypeDeclarationSQL([])
            !== $platform->getStringTypeDeclarationSQL(['length' => 36, 'fixed' => true]);
    }
}
