<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use ArrayObject;
use InvalidArgumentException;
use Phil\Enums\Collection\EnumMap;
use Phil\Enums\Collection\EnumSet;
use Phil\EnumsBundle\Infrastructure\Serializer\EnumsNormalizer;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\NoCase;
use Phil\EnumsBundle\Tests\Unit\Fixture\OrderStatus;
use Phil\EnumsBundle\Tests\Unit\Fixture\Shape;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use UnitEnum;

#[CoversClass(EnumsNormalizer::class)]
final class EnumsNormalizerTest extends TestCase
{
    public function testASetIsTheListOfItsValues(): void
    {
        self::assertSame('[1,2]', $this->serializer()->serialize(EnumSet::of(Access::class, Access::Write, Access::Read), 'json'));
        self::assertSame('["Circle"]', $this->serializer()->serialize(EnumSet::of(Shape::class, Shape::Circle), 'json'));
        self::assertSame('[]', $this->serializer()->serialize(EnumSet::none(Access::class), 'json'));
    }

    public function testAMapIsAnObjectKeyedByValuesWithItsValuesNormalized(): void
    {
        $map = EnumMap::total(Shape::class, static fn (Shape $shape): array => ['status' => OrderStatus::PENDING, 'sides' => mb_strlen($shape->name)]);

        self::assertSame(
            '{"Circle":{"status":"pending","sides":6},"BigSquare":{"status":"pending","sides":9},"Blank":{"status":"pending","sides":5},"Éclair":{"status":"pending","sides":6}}',
            $this->serializer()->serialize($map, 'json', [JsonEncode::OPTIONS => \JSON_UNESCAPED_UNICODE]),
        );
        self::assertSame('{"1":true,"2":true,"4":false}', $this->serializer()->serialize(EnumMap::total(Access::class, static fn (Access $access): bool => 4 !== $access->value), 'json'));
    }

    public function testAnEmptyMapIsAnObject(): void
    {
        $normalizer = new EnumsNormalizer();
        $map = EnumMap::total(NoCase::class, static fn (): int => 0);

        self::assertEquals(new ArrayObject(), $normalizer->normalize($map));
    }

    public function testACaseIsWrittenWithItsLabelsOnlyWhenAsked(): void
    {
        $serializer = $this->serializer($this->translator());

        self::assertSame('"pending"', $serializer->serialize(OrderStatus::PENDING, 'json'));
        self::assertSame(
            '{"value":"pending","label":"En attente","description":"En attente du paiement."}',
            $serializer->serialize(OrderStatus::PENDING, 'json', [EnumsNormalizer::LABELS => true]),
        );
        self::assertSame(
            '{"value":"cancelled","label":"Cancelled","description":null}',
            $serializer->serialize(OrderStatus::CANCELLED, 'json', [EnumsNormalizer::LABELS => true]),
        );
        self::assertSame(
            '{"value":"Circle","label":"Round","description":null}',
            $serializer->serialize(Shape::Circle, 'json', [EnumsNormalizer::LABELS => true]),
        );
    }

    public function testWithoutTranslatorTheLabelsAreWritten(): void
    {
        self::assertSame(
            ['value' => 'pending', 'label' => 'Pending', 'description' => 'Waiting for payment.'],
            new EnumsNormalizer()->normalize(OrderStatus::PENDING),
        );
        self::assertSame(
            ['value' => 4, 'label' => 'Admin', 'description' => 'Every right.'],
            new EnumsNormalizer($this->translator())->normalize(Access::from(4)),
        );
    }

    public function testWhatItSupports(): void
    {
        $normalizer = new EnumsNormalizer();

        self::assertTrue($normalizer->supportsNormalization(EnumSet::none(Access::class)));
        self::assertTrue($normalizer->supportsNormalization(EnumMap::total(Access::class, static fn (): int => 0)));
        self::assertFalse($normalizer->supportsNormalization(Access::Read));
        self::assertFalse($normalizer->supportsNormalization(Access::Read, null, [EnumsNormalizer::LABELS => 'yes']));
        self::assertTrue($normalizer->supportsNormalization(Access::Read, null, [EnumsNormalizer::LABELS => true]));
        self::assertFalse($normalizer->supportsNormalization(new stdClass(), null, [EnumsNormalizer::LABELS => true]));
        self::assertSame([EnumSet::class => true, EnumMap::class => true, UnitEnum::class => false], $normalizer->getSupportedTypes(null));
    }

    public function testAnythingElseIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(EnumsNormalizer::class.' writes enum sets, enum maps and enum cases, not stdClass.');

        new EnumsNormalizer()->normalize(new stdClass());
    }

    public function testASetIsReadBackWhenTheContextNamesItsEnum(): void
    {
        $set = $this->serializer()->deserialize('[4,1]', EnumSet::class, 'json', [EnumsNormalizer::ENUM => Access::class]);

        self::assertInstanceOf(EnumSet::class, $set);
        self::assertSame([Access::Read, Access::from(4)], [...$set]);
    }

    public function testAMapIsReadBackWithItsValuesDenormalizedWhenTheirTypeIsGiven(): void
    {
        $serializer = $this->serializer();
        $plain = $serializer->deserialize('{"Circle":1,"BigSquare":2,"Blank":3,"Éclair":4}', EnumMap::class, 'json', [EnumsNormalizer::ENUM => Shape::class]);
        $typed = $serializer->deserialize('{"1":"pending","2":"refunded","4":"pending"}', EnumMap::class, 'json', [EnumsNormalizer::ENUM => Access::class, EnumsNormalizer::VALUE_TYPE => OrderStatus::class]);

        self::assertInstanceOf(EnumMap::class, $plain);
        self::assertSame([1, 2, 3, 4], $plain->values());
        self::assertInstanceOf(EnumMap::class, $typed);
        self::assertSame([OrderStatus::PENDING, OrderStatus::REFUNDED, OrderStatus::PENDING], $typed->values());
    }

    public function testReadingNeedsTheEnumInTheContext(): void
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('Reading an '.EnumSet::class.' needs its enum in the context option "phil_enums.enum".');

        $this->serializer()->deserialize('[1]', EnumSet::class, 'json', [EnumsNormalizer::ENUM => 'stdClass']);
    }

    /**
     * @return iterable<string, array{class-string, string, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'a set that is not a list' => [EnumSet::class, '1', 'An '.EnumSet::class.' is read from an array.'];
        yield 'a set with a value no case has' => [EnumSet::class, '[1,3]', 'No case of enum '.Access::class.' has the value 3.'];
        yield 'a set with something else than a value' => [EnumSet::class, '[[1]]', 'A set holds values, not array.'];
        yield 'a map that misses a case' => [EnumMap::class, '{"1":true}', 'missing: [2, 4]'];
    }

    /**
     * @param class-string $type
     */
    #[DataProvider('unreadable')]
    public function testUnreadableDataIsNotNormalizable(string $type, string $json, string $message): void
    {
        try {
            $this->serializer()->deserialize($json, $type, 'json', [EnumsNormalizer::ENUM => Access::class, 'deserialization_path' => 'access']);
            self::fail('The data was read.');
        } catch (NotNormalizableValueException $notNormalizableValueException) {
            self::assertStringContainsString($message, $notNormalizableValueException->getMessage());
            self::assertSame('access', $notNormalizableValueException->getPath());
            self::assertTrue($notNormalizableValueException->canUseMessageForUser());
            self::assertSame(['array'], $notNormalizableValueException->getExpectedTypes());
            self::assertSame(0, $notNormalizableValueException->getCode());
        }
    }

    public function testWhatItReads(): void
    {
        $normalizer = new EnumsNormalizer();

        self::assertTrue($normalizer->supportsDenormalization([], EnumSet::class));
        self::assertTrue($normalizer->supportsDenormalization([], EnumMap::class));
        self::assertFalse($normalizer->supportsDenormalization('read', Access::class));
    }

    private function serializer(?Translator $translator = null): Serializer
    {
        return new Serializer([new EnumsNormalizer($translator), new BackedEnumNormalizer()], [new JsonEncoder()]);
    }

    private function translator(): Translator
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Pending' => 'En attente', 'Waiting for payment.' => 'En attente du paiement.', 'Admin' => 'Administration'], 'fr', 'enums');

        return $translator;
    }
}
