<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Unit\Infrastructure;

use Locale;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Form\MeasurementTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Form\Exception\TransformationFailedException;

#[CoversClass(MeasurementTransformer::class)]
final class MeasurementTransformerTest extends TestCase
{
    private string $locale;

    private UnitRegistry $registry;

    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
        $this->registry = UnitRegistry::standard();
    }

    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    private function transformer(string ...$offered): MeasurementTransformer
    {
        return new MeasurementTransformer($this->registry, array_values($offered), $this->registry->unit($offered[0]));
    }

    public function testAMeasurementIsShownInTheLocale(): void
    {
        self::assertSame(['value' => '12,5', 'unit' => 'g'], $this->transformer('kg', 'g')->transform($this->registry->measurement('12.5', 'g')));
        self::assertSame(['value' => '', 'unit' => 'kg'], $this->transformer('kg', 'g')->transform(null));
    }

    public function testAMeasurementInAnotherUnitIsShownInTheDefaultOne(): void
    {
        self::assertSame(['value' => '0,45359237', 'unit' => 'kg'], $this->transformer('kg', 'g')->transform($this->registry->measurement('1', '[lb_av]')));
        self::assertSame(['value' => '2,204623', 'unit' => '[lb_av]'], $this->transformer('[lb_av]')->transform($this->registry->measurement('1', 'kg')));
    }

    public function testAMeasurementOfAnotherFamilyFails(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->transformer('kg')->transform($this->registry->measurement('1', 'm'));
    }

    public function testAValueThatIsNotAMeasurementFails(): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->expectExceptionMessageIsOrContains('A measurement was expected, stdClass given.');
        $this->transformer('kg')->transform(new stdClass());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function submitted(): iterable
    {
        yield 'locale separator' => ['12,5', 'g', '12.5 g'];
        yield 'dot' => ['12.5', 'g', '12.5 g'];
        yield 'spaces' => [" 1\u{202F}234,5\u{a0}", 'kg', '1234.5 kg'];
        yield 'minus sign' => ["\u{2212}3", 'kg', '-3 kg'];
        yield 'integer' => ['7', 'kg', '7 kg'];
    }

    #[DataProvider('submitted')]
    public function testASubmittedValueIsRead(string $value, string $unit, string $expected): void
    {
        self::assertSame($expected, $this->transformer('kg', 'g')->reverseTransform(['value' => $value, 'unit' => $unit])?->toString());
    }

    public function testAFormWithOneUnitTakesItsValueInIt(): void
    {
        self::assertSame('3 kg', $this->transformer('kg')->reverseTransform(['value' => '3', 'unit' => 'g'])?->toString());
        self::assertSame('3 kg', $this->transformer('kg')->reverseTransform(['value' => '3'])?->toString());
    }

    public function testAnEmptyValueIsNoMeasurement(): void
    {
        self::assertNull($this->transformer('kg')->reverseTransform(['value' => '  ', 'unit' => 'kg']));
        self::assertNull($this->transformer('kg')->reverseTransform(['value' => null]));
        self::assertNull($this->transformer('kg')->reverseTransform('12'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function refused(): iterable
    {
        yield 'quotient' => [['value' => '1/3', 'unit' => 'kg']];
        yield 'two separators' => [['value' => '1,5,5', 'unit' => 'kg']];
        yield 'letters' => [['value' => '12 kg', 'unit' => 'kg']];
        yield 'unit not offered' => [['value' => '12', 'unit' => 'm']];
        yield 'no unit' => [['value' => '12']];
        yield 'too many digits' => [['value' => '1234567890123456789', 'unit' => 'kg']];
        yield 'line break' => [['value' => "12\n", 'unit' => 'kg']];
    }

    public function testALineBreakIsRefusedByTheFieldNotByTheLibrary(): void
    {
        try {
            $this->transformer('kg', 'g')->reverseTransform(['value' => "12\n", 'unit' => 'kg']);
            self::fail('A value that ends in a line break is refused.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame("\"12\n\" is not a measurement in an offered unit.", $transformationFailedException->getMessage());
        }
    }

    public function testAnUnreadableValueIsShownCutAtSixtyFourBytes(): void
    {
        $long = 'X'.str_repeat('a', 69);

        try {
            $this->transformer('kg', 'g')->reverseTransform(['value' => $long, 'unit' => 'kg']);
            self::fail('Letters are refused.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame(\sprintf('"%s" is not a measurement in an offered unit.', 'X'.str_repeat('a', 63)), $transformationFailedException->getMessage());
        }

        try {
            $this->transformer('kg', 'g')->reverseTransform(['value' => str_repeat('a', 63).'étail', 'unit' => 'kg']);
            self::fail('Letters are refused.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertSame(\sprintf('"%s" is not a measurement in an offered unit.', str_repeat('a', 63)), $transformationFailedException->getMessage());
        }
    }

    public function testTheLibrarysRefusalIsKeptAsThePrevious(): void
    {
        try {
            $this->transformer('kg', 'g')->reverseTransform(['value' => '1234567890123456789', 'unit' => 'kg']);
            self::fail('More than 18 digits are refused.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertInstanceOf(MeasurementException::class, $transformationFailedException->getPrevious());
            self::assertSame(0, $transformationFailedException->getCode());
        }

        try {
            $this->transformer('kg')->transform($this->registry->measurement('1', 'm'));
            self::fail('A length is not a mass.');
        } catch (TransformationFailedException $transformationFailedException) {
            self::assertInstanceOf(MeasurementException::class, $transformationFailedException->getPrevious());
            self::assertSame(0, $transformationFailedException->getCode());
        }
    }

    /**
     * @param array<string, mixed> $value
     */
    #[DataProvider('refused')]
    public function testAnUnreadableValueFails(array $value): void
    {
        $this->expectException(TransformationFailedException::class);
        $this->transformer('kg', 'g')->reverseTransform($value);
    }
}
