<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Phil\DddBundle\Domain\Exception\DomainError;
use ReflectionClass;
use ReflectionNamedType;

/**
 * The identifiers of content roots, between the string a `ContentReference` holds and what the database stores.
 *
 * A string becomes an identifier by the type of the root's identifier property, so a lookup binds the value its
 * own DBAL type expects, on every platform:
 *
 * - an `int` property reads a string of digits, an auto-incremented key;
 * - a `string` property reads the string as it is;
 * - a class with a static `fromString()` — every `Identifier` of `phil/ddd-bundle`, a `Uuid` of
 *   `symfony/uid` — is built through it; a string it refuses, by a `DomainError` or an
 *   `InvalidArgumentException`, is no identifier.
 *
 * Identifiers are bound in a list (`IN (:ids)`) as the database values of the identifier's DBAL type, with the
 * array type of its binding type: a DQL parameter would not otherwise know how to convert an identifier object.
 */
final readonly class RootIdentifiers
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * The identifier `$value` names in the root class `$class`, or `null` when it cannot be one of its type.
     *
     * @param class-string $class
     */
    public function fromString(string $class, string $value): mixed
    {
        $type = $this->propertyType($class, $this->entityManager->getClassMetadata($class)->getSingleIdentifierFieldName());

        if (null === $type || 'string' === $type) {
            return $value;
        }

        if ('int' === $type) {
            // At most 18 digits, which always fit a 64-bit integer.
            return \strlen($value) <= 18 && strspn($value, '0123456789') === \strlen($value) ? (int) $value : null;
        }

        if (!method_exists($type, 'fromString')) {
            return $value;
        }

        try {
            return $type::fromString($value);
        } catch (DomainError|InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Identifiers of `$class` as the database stores them, and the array type that binds them.
     *
     * @param class-string $class
     * @param list<mixed>  $identifiers
     *
     * @return array{list<mixed>, ArrayParameterType}
     */
    public function bindable(string $class, array $identifiers): array
    {
        $metadata = $this->entityManager->getClassMetadata($class);
        $typeName = $metadata->getTypeOfField($metadata->getSingleIdentifierFieldName());

        if (null === $typeName) {
            return [$identifiers, ArrayParameterType::STRING];
        }

        $type = Type::getType($typeName);
        $platform = $this->entityManager->getConnection()->getDatabasePlatform();

        return [
            array_map(static fn (mixed $identifier): mixed => $type->convertToDatabaseValue($identifier, $platform), $identifiers),
            match ($type->getBindingType()) {
                ParameterType::INTEGER => ArrayParameterType::INTEGER,
                ParameterType::ASCII => ArrayParameterType::ASCII,
                ParameterType::BINARY, ParameterType::LARGE_OBJECT => ArrayParameterType::BINARY,
                default => ArrayParameterType::STRING,
            },
        ];
    }

    /**
     * The declared type of the identifier property, looked up through the parents for a private one.
     *
     * @param class-string $class
     */
    private function propertyType(string $class, string $field): ?string
    {
        for ($reflection = new ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            if ($reflection->hasProperty($field)) {
                $type = $reflection->getProperty($field)->getType();

                return $type instanceof ReflectionNamedType ? $type->getName() : null;
            }
        }

        return null;
    }
}
