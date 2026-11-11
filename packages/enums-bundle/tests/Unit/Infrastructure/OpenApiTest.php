<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use ApiPlatform\JsonSchema\Metadata\Property\Factory\SchemaPropertyMetadataFactory;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use Nelmio\ApiDocBundle\Model\Model;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use Override;
use Phil\EnumsBundle\Infrastructure\OpenApi\ApiPlatformEnumPropertyMetadataFactory;
use Phil\EnumsBundle\Infrastructure\OpenApi\NelmioEnumModelDescriber;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\TypeInfo\Type;

#[CoversClass(NelmioEnumModelDescriber::class)]
#[CoversClass(ApiPlatformEnumPropertyMetadataFactory::class)]
final class OpenApiTest extends TestCase
{
    public function testNelmioDescribesAnyEnumModel(): void
    {
        $describer = new NelmioEnumModelDescriber();

        self::assertTrue($describer->supports(new Model(Type::enum(Access::class))));
        self::assertTrue($describer->supports(new Model(Type::enum(Shape::class))));
        self::assertFalse($describer->supports(new Model(Type::object(stdClass::class))));
        self::assertFalse($describer->supports(new Model(Type::string())));
    }

    public function testNelmioWritesTheValuesNamesDescriptionsAndDefault(): void
    {
        $schema = new Schema([]);
        new NelmioEnumModelDescriber()->describe(new Model(Type::enum(Access::class)), $schema);

        self::assertSame('integer', $schema->type);
        self::assertSame([1, 2], $schema->enum);
        self::assertSame(['enum-varnames' => ['Read', 'Write'], 'enum-descriptions' => ['Read', 'Write']], $schema->x);
        self::assertSame(1, $schema->default);
    }

    public function testNelmioWritesAPureEnumByItsNamesAndNoDefaultWithoutOne(): void
    {
        $schema = new Schema([]);
        new NelmioEnumModelDescriber()->describe(new Model(Type::enum(Shape::class)), $schema);

        self::assertSame('string', $schema->type);
        self::assertSame(['Circle', 'BigSquare', 'Blank', 'Éclair'], $schema->enum);
        self::assertTrue(Generator::isDefault($schema->default));
    }

    public function testNelmioLeavesAnotherModelAlone(): void
    {
        $schema = new Schema([]);
        new NelmioEnumModelDescriber()->describe(new Model(Type::object(stdClass::class)), $schema);
        new NelmioEnumModelDescriber()->describe(new Model(Type::string()), $schema);

        self::assertTrue(Generator::isDefault($schema->type));
        self::assertTrue(Generator::isDefault($schema->enum));
    }

    public function testApiPlatformCompletesTheSchemaOfAnEnumProperty(): void
    {
        $metadata = $this->factory(new ApiProperty(schema: ['type' => 'string', 'enum' => ['pending'], 'description' => 'The status.'], nativeType: Type::enum(OrderStatus::class)))->create('App\Order', 'status');

        self::assertSame([
            'type' => 'string',
            'enum' => ['pending', 'in_transit', 'cancelled', 'refunded'],
            'description' => 'The status.',
            'x-enum-varnames' => ['PENDING', 'IN_TRANSIT', 'CANCELLED', 'REFUNDED'],
            'x-enum-descriptions' => ['Waiting for payment.', 'The parcel has left.', 'Cancelled', 'REFUNDED'],
        ], $metadata->getSchema());
    }

    public function testApiPlatformKeepsNullForANullableProperty(): void
    {
        $schema = $this->factory(new ApiProperty(nativeType: Type::nullable(Type::enum(Access::class))))->create('App\Member', 'access')->getSchema();

        self::assertIsArray($schema);
        self::assertSame(['integer', 'null'], $schema['type']);
        self::assertSame([1, 2, null], $schema['enum']);
        self::assertSame(1, $schema['default']);
    }

    public function testApiPlatformDescribesAPureEnum(): void
    {
        $schema = $this->factory(new ApiProperty(nativeType: Type::enum(Shape::class)))->create('App\Drawing', 'shape')->getSchema();

        self::assertIsArray($schema);
        self::assertSame(['Circle', 'BigSquare', 'Blank', 'Éclair'], $schema['enum']);
    }

    public function testApiPlatformLeavesOtherPropertiesAndDeclaredSchemasAlone(): void
    {
        $plain = new ApiProperty(schema: ['type' => 'string'], nativeType: Type::string());
        $untyped = new ApiProperty();
        $declared = new ApiProperty(schema: ['type' => 'string'], nativeType: Type::enum(Access::class), extraProperties: [SchemaPropertyMetadataFactory::JSON_SCHEMA_USER_DEFINED => true]);

        self::assertSame($plain, $this->factory($plain)->create('App\Member', 'name'));
        self::assertSame($untyped, $this->factory($untyped)->create('App\Member', 'note'));
        self::assertSame($declared, $this->factory($declared)->create('App\Member', 'access'));
        self::assertSame(['type' => 'string'], $this->factory(new ApiProperty(schema: ['type' => 'string'], nativeType: Type::object(stdClass::class)))->create('App\Member', 'thing')->getSchema());
    }

    public function testApiPlatformPassesTheOptionsOn(): void
    {
        $inner = new class implements PropertyMetadataFactoryInterface {
            /** @var list<mixed> */
            public array $options = [];

            #[Override]
            public function create(string $resourceClass, string $property, array $options = []): ApiProperty
            {
                $this->options = [$resourceClass, $property, $options];

                return new ApiProperty();
            }
        };

        new ApiPlatformEnumPropertyMetadataFactory($inner)->create('App\Member', 'access', ['schema_type' => 'input']);

        self::assertSame(['App\Member', 'access', ['schema_type' => 'input']], $inner->options);
    }

    private function factory(ApiProperty $metadata): ApiPlatformEnumPropertyMetadataFactory
    {
        return new ApiPlatformEnumPropertyMetadataFactory(new readonly class($metadata) implements PropertyMetadataFactoryInterface {
            public function __construct(
                private ApiProperty $metadata,
            ) {
            }

            #[Override]
            public function create(string $resourceClass, string $property, array $options = []): ApiProperty
            {
                return $this->metadata;
            }
        });
    }
}
