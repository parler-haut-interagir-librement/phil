<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\OpenApi;

use ApiPlatform\JsonSchema\Metadata\Property\Factory\SchemaPropertyMetadataFactory;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Override;
use Phil\EnumsBundle\Application\Schema\EnumSchema;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use UnitEnum;

/**
 * Completes the JSON Schema API Platform gives a property typed by an enum with `EnumSchema`: the values — names
 * for a pure enum, which API Platform does not describe —, the names and descriptions client generators read
 * (`x-enum-varnames`, `x-enum-descriptions`) and the `#[DefaultCase]`. A nullable property keeps `null` among its
 * values and types. A schema the property declares itself is left as written, as API Platform leaves it.
 *
 * It decorates the property metadata factory after API Platform's own schema factory.
 */
final readonly class ApiPlatformEnumPropertyMetadataFactory implements PropertyMetadataFactoryInterface
{
    public function __construct(
        private PropertyMetadataFactoryInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Override]
    public function create(string $resourceClass, string $property, array $options = []): ApiProperty
    {
        $metadata = $this->decorated->create($resourceClass, $property, $options);
        $type = $metadata->getNativeType();
        $enum = $this->enum($type);

        if (null === $enum || true === ($metadata->getExtraProperties()[SchemaPropertyMetadataFactory::JSON_SCHEMA_USER_DEFINED] ?? false)) {
            return $metadata;
        }

        $described = EnumSchema::of($enum);
        $nullable = $type instanceof NullableType;

        return $metadata->withSchema([
            ...$metadata->getSchema() ?? [],
            ...$described,
            'type' => $nullable ? [$described['type'], 'null'] : $described['type'],
            'enum' => $nullable ? [...$described['enum'], null] : $described['enum'],
        ]);
    }

    /**
     * The enum a type names, nullable or not.
     *
     * @param Type<mixed>|null $type
     *
     * @return class-string<UnitEnum>|null
     */
    private function enum(?Type $type): ?string
    {
        $type = $type instanceof NullableType ? $type->getWrappedType() : $type;

        return $type instanceof ObjectType && is_subclass_of($type->getClassName(), UnitEnum::class) ? $type->getClassName() : null;
    }
}
