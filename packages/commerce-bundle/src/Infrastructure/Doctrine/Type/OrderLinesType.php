<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;
use Override;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;

/**
 * The lines of an order in one JSON column: a list of objects, each with its reference, description, quantity,
 * unit price in minor units, currency and tax category, and the `configuration` of a configured line: its `version`
 * (`LineConfiguration::VERSION`) and its `options`, by code, each with its `value` (`null` for a value to draw), its
 * `label`, its `display`, its `drawer` and its `draws`. A row without the key is a line without options.
 *
 * Every line is rebuilt through its own constructors on the way out, so a stored value no code of the application
 * could have written is refused as a DBAL conversion error rather than read into an order.
 */
final class OrderLinesType extends Type
{
    public const string NAME = 'phil_commerce_order_lines';

    /**
     * @param array<string, mixed> $column
     */
    #[Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    #[Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }

        $lines = [];

        foreach ($value as $line) {
            if (!$line instanceof OrderLine) {
                throw InvalidType::new($line, self::class, [OrderLine::class]);
            }

            $row = [
                'type' => $line->reference->type,
                'id' => $line->reference->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice->minorUnits,
                'currency' => $line->unitPrice->currency->code,
                'tax_category' => $line->taxCategory,
            ];

            if (!$line->configuration->isEmpty()) {
                $row['configuration'] = $this->configurationRow($line->configuration);
            }

            $lines[] = $row;
        }

        return json_encode($lines, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, OrderLine>
     */
    #[Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        if (!\is_string($value)) {
            throw ValueNotConvertible::new(get_debug_type($value), self::class);
        }

        try {
            $rows = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw ValueNotConvertible::new($value, self::class, null, $jsonException);
        }

        if (!\is_array($rows) || !array_is_list($rows)) {
            throw ValueNotConvertible::new($value, self::class, 'a JSON list of order lines');
        }

        $lines = [];

        foreach ($rows as $row) {
            if (!\is_array($row)
                || !\is_string($row['type'] ?? null) || !\is_string($row['id'] ?? null) || !\is_string($row['description'] ?? null)
                || !\is_int($row['quantity'] ?? null) || !\is_int($row['unit_price'] ?? null)
                || !\is_string($row['currency'] ?? null) || !\is_string($row['tax_category'] ?? null)) {
                throw ValueNotConvertible::new($value, self::class, 'order lines with a type, id, description, quantity, unit_price, currency and tax_category');
            }

            try {
                $price = Money::of($row['unit_price'], Currency::of($row['currency']));
                $configuration = \array_key_exists('configuration', $row) ? $this->configuration($row['configuration']) : LineConfiguration::none();

                if (!$configuration instanceof LineConfiguration) {
                    throw ValueNotConvertible::new($value, self::class, \sprintf('a configuration of version %d, with its options', LineConfiguration::VERSION));
                }

                $line = OrderLine::stored(Sellable::of(SellableReference::of($row['type'], $row['id']), $row['description'], $price, $row['tax_category']), $row['quantity'], $price, $configuration);
            } catch (DomainError|MoneyException $invalid) {
                throw ValueNotConvertible::new($value, self::class, null, $invalid);
            }

            $lines[$line->reference->value()] = $line;
        }

        return $lines;
    }

    /**
     * @return array{version: int, options: array<string, array{value: ?string, label: ?string, display: ?string, drawer: ?string, draws: list<string>}>}
     */
    private function configurationRow(LineConfiguration $configuration): array
    {
        $options = [];

        foreach ($configuration->values as $code => $option) {
            $options[$code] = [
                'value' => $option->value,
                'label' => $option->label,
                'display' => $option->display,
                'drawer' => $option->drawer,
                'draws' => $option->draws,
            ];
        }

        return ['version' => LineConfiguration::VERSION, 'options' => $options];
    }

    /**
     * The configuration a row holds, or `null` when it is not one of the version this type writes.
     */
    private function configuration(mixed $row): ?LineConfiguration
    {
        if (!\is_array($row) || LineConfiguration::VERSION !== ($row['version'] ?? null) || !\is_array($row['options'] ?? null) || [] === $row['options']) {
            return null;
        }

        $values = [];

        foreach ($row['options'] as $code => $option) {
            if (!\is_string($code) || !\is_array($option) || !\array_key_exists('value', $option) || !\array_key_exists('display', $option) || !\array_key_exists('drawer', $option)) {
                return null;
            }

            ['value' => $chosen, 'display' => $display, 'drawer' => $drawer] = $option;
            $label = $option['label'] ?? null;
            $draws = $option['draws'] ?? null;

            if (!\is_string($label) || (null !== $chosen && !\is_string($chosen)) || (null !== $display && !\is_string($display)) || (null !== $drawer && !\is_string($drawer))
                || !\is_array($draws) || !array_is_list($draws) || [] !== array_filter($draws, static fn (mixed $draw): bool => !\is_string($draw))) {
                return null;
            }

            /** @var list<string> $draws */
            $value = (null === $chosen ? OptionValue::toDraw() : OptionValue::chosen($chosen))->described($label, $display, $drawer);
            $values[$code] = [] === $draws ? $value : $value->withDraws($draws);
        }

        return LineConfiguration::of($values);
    }
}
