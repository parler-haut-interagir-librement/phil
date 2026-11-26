<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\Serializer;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DddBundle\Domain\Identifier\Identifier;
use ReflectionClass;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * An identifier in and out of the Serializer as its canonical string, `0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e`.
 *
 * Without it, the `ObjectNormalizer` would print an identifier as `{"value": {…}}` and could not build one back:
 * the constructor of `Identifier` is private.
 *
 * **A value that is not an identifier of the declared class is a `NotNormalizableValueException` whose message
 * may be shown to the user**, at the path the Serializer reports: what failed is the data a client sent, not the
 * code. The message names the expected form, never the value received nor the class.
 *
 * Denormalising to `Identifier` itself, or to an abstract subclass, is not supported: no class to build is named.
 *
 * Stateless: one instance serves every request of a worker.
 */
final readonly class IdentifierNormalizer implements NormalizerInterface, DenormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): string
    {
        if (!$data instanceof Identifier) {
            throw new InvalidArgumentException(\sprintf('The object must be an instance of "%s".', Identifier::class));
        }

        return $data->toString();
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Identifier;
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (!$this->isConcreteIdentifier($type)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a concrete subclass of "%s".', $type, Identifier::class));
        }

        $path = $context['deserialization_path'] ?? null;
        $path = \is_string($path) ? $path : null;

        if (!\is_string($data)) {
            throw $this->unexpected($data, $path);
        }

        try {
            return $type::fromString($data);
        } catch (InvalidValue $invalidValue) {
            throw $this->unexpected($data, $path, $invalidValue);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $this->isConcreteIdentifier($type);
    }

    /**
     * Both answers depend on the type alone, so the Serializer may cache them.
     *
     * @return array<class-string, bool>
     */
    #[Override]
    public function getSupportedTypes(?string $format): array
    {
        return [Identifier::class => true];
    }

    /**
     * @phpstan-assert-if-true class-string<Identifier> $type
     */
    private function isConcreteIdentifier(string $type): bool
    {
        return is_subclass_of($type, Identifier::class) && !new ReflectionClass($type)->isAbstract();
    }

    private function unexpected(mixed $data, ?string $path, ?InvalidValue $previous = null): NotNormalizableValueException
    {
        return NotNormalizableValueException::createForUnexpectedDataType(
            'The data is not an identifier: a UUIDv7 string is expected.',
            $data,
            ['string'],
            $path,
            true,
            0,
            $previous,
        );
    }
}
