<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Serializer;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DddBundle\Domain\Identifier\Identifier;
use Phil\DddBundle\Infrastructure\Serializer\IdentifierNormalizer;
use Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture\AbstractExampleId;
use Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture\ExampleId;
use Phil\DddBundle\Tests\Unit\Domain\Identifier\Fixture\OtherId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Uid\Uuid;

#[CoversClass(IdentifierNormalizer::class)]
final class IdentifierNormalizerTest extends TestCase
{
    public function testItNormalisesAnIdentifierToItsCanonicalString(): void
    {
        $identifier = ExampleId::fromString('0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e');

        self::assertSame('0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e', new IdentifierNormalizer()->normalize($identifier));
    }

    public function testItRefusesToNormaliseAnythingElse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The object must be an instance of "%s".', Identifier::class));

        new IdentifierNormalizer()->normalize(new stdClass());
    }

    public function testItSupportsTheNormalisationOfIdentifiersOnly(): void
    {
        $normalizer = new IdentifierNormalizer();

        self::assertTrue($normalizer->supportsNormalization(ExampleId::generate()));
        self::assertTrue($normalizer->supportsNormalization(OtherId::generate(), 'json'));
        self::assertFalse($normalizer->supportsNormalization(new stdClass()));
        self::assertFalse($normalizer->supportsNormalization('0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e'));
    }

    public function testItDenormalisesToTheRequestedClass(): void
    {
        $identifier = OtherId::generate();

        $denormalized = new IdentifierNormalizer()->denormalize($identifier->toString(), OtherId::class);

        self::assertInstanceOf(OtherId::class, $denormalized);
        self::assertTrue($identifier->equals($denormalized));
    }

    public function testItSupportsTheDenormalisationOfConcreteIdentifierClassesOnly(): void
    {
        $normalizer = new IdentifierNormalizer();
        $data = ExampleId::generate()->toString();

        self::assertTrue($normalizer->supportsDenormalization($data, ExampleId::class));
        self::assertFalse($normalizer->supportsDenormalization($data, Identifier::class));
        self::assertFalse($normalizer->supportsDenormalization($data, AbstractExampleId::class));
        self::assertFalse($normalizer->supportsDenormalization($data, stdClass::class));
        self::assertFalse($normalizer->supportsDenormalization($data, 'string'));
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function unsupportedTypes(): iterable
    {
        yield 'the base class' => [Identifier::class];
        yield 'an abstract subclass' => [AbstractExampleId::class];
        yield 'another class' => [stdClass::class];
    }

    #[DataProvider('unsupportedTypes')]
    public function testItRefusesToDenormaliseToAnUnsupportedType(string $type): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a concrete subclass of "%s".', $type, Identifier::class));

        new IdentifierNormalizer()->denormalize(ExampleId::generate()->toString(), $type);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidData(): iterable
    {
        yield 'not a UUID' => ['philippe', 'string'];
        yield 'a UUID, but a v4' => [Uuid::v4()->toRfc4122(), 'string'];
        yield 'an integer' => [42, 'int'];
        yield 'null' => [null, 'null'];
        yield 'an array' => [['0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e'], 'array'];
    }

    /**
     * The client sent it: the message may reach the user, at the path the Serializer reports, and names neither the
     * value nor the class.
     */
    #[DataProvider('invalidData')]
    public function testDataThatIsNotAnIdentifierIsNotNormalizable(mixed $data, string $currentType): void
    {
        try {
            new IdentifierNormalizer()->denormalize($data, ExampleId::class, 'json', ['deserialization_path' => 'order.id']);
            self::fail('Data that is not an identifier must be refused.');
        } catch (NotNormalizableValueException $notNormalizableValueException) {
            self::assertSame('The data is not an identifier: a UUIDv7 string is expected.', $notNormalizableValueException->getMessage());
            self::assertTrue($notNormalizableValueException->canUseMessageForUser());
            self::assertSame('order.id', $notNormalizableValueException->getPath());
            self::assertSame(['string'], $notNormalizableValueException->getExpectedTypes());
            self::assertSame($currentType, $notNormalizableValueException->getCurrentType());
            self::assertSame(0, $notNormalizableValueException->getCode());
        }
    }

    public function testTheRefusalOfTheIdentifierIsKeptAsThePreviousException(): void
    {
        try {
            new IdentifierNormalizer()->denormalize('philippe', ExampleId::class);
            self::fail('A value that is not a UUID must be refused.');
        } catch (NotNormalizableValueException $notNormalizableValueException) {
            self::assertInstanceOf(InvalidValue::class, $notNormalizableValueException->getPrevious());
            self::assertNull($notNormalizableValueException->getPath());
        }
    }

    public function testANonStringValueHasNoPreviousException(): void
    {
        try {
            new IdentifierNormalizer()->denormalize(42, ExampleId::class);
            self::fail('An integer must be refused.');
        } catch (NotNormalizableValueException $notNormalizableValueException) {
            self::assertNull($notNormalizableValueException->getPrevious());
        }
    }

    public function testAPathThatIsNotAStringIsLeftOut(): void
    {
        try {
            new IdentifierNormalizer()->denormalize('philippe', ExampleId::class, null, ['deserialization_path' => 42]);
            self::fail('A value that is not a UUID must be refused.');
        } catch (NotNormalizableValueException $notNormalizableValueException) {
            self::assertNull($notNormalizableValueException->getPath());
        }
    }

    public function testItDeclaresIdentifiersAsCacheableForEveryFormat(): void
    {
        $normalizer = new IdentifierNormalizer();

        self::assertSame([Identifier::class => true], $normalizer->getSupportedTypes(null));
        self::assertSame([Identifier::class => true], $normalizer->getSupportedTypes('json'));
    }
}
