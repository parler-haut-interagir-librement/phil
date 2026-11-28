<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Infrastructure\Form;

use Locale;
use NumberFormatter;
use Override;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\Measurement;
use Phil\Measurement\Unit;
use Phil\Measurement\UnitRegistry;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Between a `Measurement` and the values of the `value` and `unit` fields of `MeasurementType`: the value as a decimal
 * in the request's locale, the unit by its code.
 *
 * A measurement in a unit the form does not offer is shown in the default unit, converted exactly; one of another
 * family fails the form. A form without a `unit` field takes its value in its one unit.
 *
 * The value is read as a person writes it: digits, an optional `-` (or `−`), and the locale's decimal separator or a
 * dot followed by digits; spaces are ignored, and nothing else is read, so that `1,5` is never read as fifteen. An
 * empty value is no measurement (`null`).
 *
 * @implements DataTransformerInterface<Measurement, array{value?: ?string, unit?: ?string}>
 */
final readonly class MeasurementTransformer implements DataTransformerInterface
{
    private const array SPACES = [' ', "\u{00A0}", "\u{202F}"];

    /**
     * @param list<string> $offered the codes of the units offered, the default one among them
     */
    public function __construct(
        private UnitRegistry $registry,
        private array $offered,
        private Unit $default,
    ) {
    }

    /**
     * @param mixed $value the form's data, which the application may have set to anything
     *
     * @return array{value: string, unit: string}
     */
    #[Override]
    public function transform(mixed $value): array
    {
        if (null === $value) {
            return ['value' => '', 'unit' => $this->default->code];
        }

        if (!$value instanceof Measurement) {
            throw new TransformationFailedException(\sprintf('A measurement was expected, %s given.', get_debug_type($value)));
        }

        try {
            $shown = \in_array($value->unit->code, $this->offered, true) ? $value : $value->convertTo($this->default);
        } catch (MeasurementException $measurementException) {
            throw new TransformationFailedException($measurementException->getMessage(), previous: $measurementException);
        }

        return [
            'value' => str_replace('.', $this->separator(), $shown->exactDecimal() ?? $shown->toDecimal(6)),
            'unit' => $shown->unit->code,
        ];
    }

    /**
     * @param mixed $value what the form submitted, which a request may have made anything
     */
    #[Override]
    public function reverseTransform(mixed $value): ?Measurement
    {
        $text = \is_array($value) ? ($value['value'] ?? null) : null;

        if (!\is_string($text) || '' === trim($text)) {
            return null;
        }

        $decimal = str_replace([...self::SPACES, "\u{2212}"], ['', '', '', '-'], $text);
        $decimal = str_replace($this->separator(), '.', $decimal);

        $code = 1 === \count($this->offered) ? $this->offered[0] : ($value['unit'] ?? null);

        if (1 !== preg_match('/^-?\d{1,64}(?:\.\d{1,64})?$/D', $decimal) || !\is_string($code) || !\in_array($code, $this->offered, true)) {
            throw new TransformationFailedException(\sprintf('"%s" is not a measurement in an offered unit.', mb_strcut($text, 0, 64)));
        }

        try {
            return $this->registry->measurement($decimal, $code);
        } catch (MeasurementException $measurementException) {
            throw new TransformationFailedException($measurementException->getMessage(), previous: $measurementException);
        }
    }

    private function separator(): string
    {
        return new NumberFormatter(Locale::getDefault(), NumberFormatter::DECIMAL)->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
    }
}
