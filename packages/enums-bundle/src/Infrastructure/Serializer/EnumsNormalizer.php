<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Serializer;

use ArrayObject;
use BackedEnum;
use InvalidArgumentException;
use Override;
use Phil\Enums\Collection\EnumMap;
use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Application\Translation\EnumTranslation;
use Phil\EnumsBundle\Infrastructure\Translation\TranslatableText;
use Symfony\Component\Serializer\Exception\InvalidArgumentException as SerializerInvalidArgument;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnitEnum;

/**
 * Writes the structures of `phil/enums`, and enum cases with their labels when asked:
 *
 * - **An `EnumSet`** becomes the list of its values — the names of a pure enum —, in declaration order.
 * - **An `EnumMap`** becomes an object keyed by the values of the cases, each value normalized in turn.
 * - **A case, with the context option `phil_enums.labels`**, becomes `{value, label, description}`: the label and
 *   description translated when the enum is translatable and a translator is there. Without the option, cases
 *   are left to Symfony's own normalizer.
 *
 * It reads sets and maps back when the context names their enum — `phil_enums.enum`, which the property declares
 * with `#[Context]` since a class name carries no generic type —, and the type of a map's values with
 * `phil_enums.value_type` when they need denormalizing. A case is read by its value, by Symfony's normalizer.
 */
final class EnumsNormalizer implements NormalizerInterface, NormalizerAwareInterface, DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    public const string LABELS = 'phil_enums.labels';

    public const string ENUM = 'phil_enums.enum';

    public const string VALUE_TYPE = 'phil_enums.value_type';

    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<int|string, mixed>|ArrayObject<int|string, mixed>
     */
    #[Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|ArrayObject
    {
        if ($data instanceof EnumSet) {
            return $data->values();
        }

        if ($data instanceof EnumMap) {
            $normalized = [];

            foreach ($data->toArray() as $key => $value) {
                $normalized[$key] = $this->normalizer->normalize($value, $format, $context);
            }

            // An empty map is an object, not a list.
            return [] === $normalized ? new ArrayObject() : $normalized;
        }

        return $data instanceof UnitEnum ? [
            'value' => $data instanceof BackedEnum ? $data->value : $data->name,
            'label' => $this->label($data),
            'description' => $this->description($data),
        ] : throw new InvalidArgumentException(\sprintf('%s writes enum sets, enum maps and enum cases, not %s.', self::class, get_debug_type($data)));
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof EnumSet
            || $data instanceof EnumMap
            || ($data instanceof UnitEnum && true === ($context[self::LABELS] ?? false));
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $path = \is_string($context['deserialization_path'] ?? null) ? $context['deserialization_path'] : null;
        $enum = $context[self::ENUM] ?? null;

        if (!\is_string($enum) || !enum_exists($enum)) {
            throw new SerializerInvalidArgument(\sprintf('Reading an %s needs its enum in the context option "%s".', $type, self::ENUM));
        }

        if (!\is_array($data)) {
            throw NotNormalizableValueException::createForUnexpectedDataType(\sprintf('An %s is read from an array.', $type), $data, ['array'], $path, true);
        }

        try {
            return EnumSet::class === $type ? EnumSet::fromValues($enum, $this->primitives($data)) : EnumMap::fromArray($enum, $this->values($data, $format, $context));
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw NotNormalizableValueException::createForUnexpectedDataType($invalidArgumentException->getMessage(), $data, ['array'], $path, true, 0, $invalidArgumentException);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    #[Override]
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return EnumSet::class === $type || EnumMap::class === $type;
    }

    #[Override]
    public function getSupportedTypes(?string $format): array
    {
        return [EnumSet::class => true, EnumMap::class => true, UnitEnum::class => false];
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<int|string>
     */
    private function primitives(array $data): array
    {
        return array_map(static fn (mixed $value): int|string => \is_int($value) || \is_string($value) ? $value : throw new InvalidArgumentException(\sprintf('A set holds values, not %s.', get_debug_type($value))), $data);
    }

    /**
     * @param array<mixed>         $data
     * @param array<string, mixed> $context
     *
     * @return array<mixed>
     */
    private function values(array $data, ?string $format, array $context): array
    {
        $type = $context[self::VALUE_TYPE] ?? null;

        return \is_string($type) ? array_map(fn (mixed $value): mixed => $this->denormalizer->denormalize($value, $type, $format, $context), $data) : $data;
    }

    private function label(UnitEnum $case): string
    {
        return $case instanceof TranslatableInterface && $this->translator instanceof TranslatorInterface
            ? $case->trans($this->translator)
            : EnumTranslation::plainLabel($case);
    }

    private function description(UnitEnum $case): ?string
    {
        $key = EnumTranslation::descriptionKey($case);

        return null !== $key && $case instanceof TranslatableInterface && $this->translator instanceof TranslatorInterface
            ? new TranslatableText($key, EnumTranslation::domain($case))->trans($this->translator)
            : $key;
    }
}
