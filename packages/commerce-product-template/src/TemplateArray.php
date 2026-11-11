<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Reader;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;

/**
 * A template as an array of scalars, for a JSON column or a file, and back: `fromArray()` checks every value as the
 * constructors do, and refuses an array of another version.
 */
final class TemplateArray
{
    public const int VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public static function toArray(Template $template): array
    {
        $amounts = static fn (array $byAxis): array => array_map(static fn (array $values): array => array_map(static fn (Money $money): int => $money->minorUnits, $values), $byAxis);

        return [
            'version' => self::VERSION,
            'code' => $template->code,
            'name' => $template->name->texts(),
            'sku_pattern' => $template->skuPattern->pattern,
            'supplier' => $template->supplier,
            'pricing' => [
                'currency' => $template->pricing->base->currency->code,
                'base' => $template->pricing->base->minorUnits,
                'prices' => $amounts($template->pricing->prices),
                'supplements' => $amounts($template->pricing->supplements),
            ],
            'axes' => array_map(static fn (Axis $axis): array => [
                'code' => $axis->code,
                'label' => $axis->label->texts(),
                'values' => array_map(static fn (AxisValue $value): array => [
                    'code' => $value->code,
                    'label' => $value->label->texts(),
                    'swatch' => $value->swatch instanceof Swatch ? ['hex' => $value->swatch->hex, 'photo' => $value->swatch->photo] : null,
                    'supplier_name' => $value->supplierName,
                    'photos' => $value->photos,
                    'channels' => $value->channels,
                ], $axis->values()),
            ], $template->axes()),
            'faces' => array_map(static fn (Face $face): array => [
                'code' => $face->code,
                'label' => $face->label->texts(),
                'zones' => array_map(static fn (PrintZone $zone): array => [
                    'name' => $zone->name,
                    'x' => $zone->x,
                    'y' => $zone->y,
                    'width' => $zone->width,
                    'height' => $zone->height,
                    'dpi' => $zone->dpi,
                    'photo_box' => $zone->photoBox instanceof PhotoBox ? ['x' => $zone->photoBox->x, 'y' => $zone->photoBox->y, 'width' => $zone->photoBox->width, 'height' => $zone->photoBox->height] : null,
                ], $face->zones()),
            ], $template->faces()),
            'exclusions' => array_map(static fn (Exclusion $exclusion): array => $exclusion->values, $template->exclusions),
        ];
    }

    /**
     * @param array<array-key, mixed> $data as `toArray()` writes it
     *
     * @throws InvalidDefinition for an array of another version, a value of the wrong type, or a template refused
     */
    public static function fromArray(array $data): Template
    {
        $root = new Reader($data, 'template');
        $root->version(self::VERSION);

        $pricing = $root->reader('pricing');

        try {
            $currency = Currency::of($pricing->string('currency'));
            $result = Pricing::of(Money::of($pricing->int('base'), $currency));

            foreach (self::amounts($pricing, 'prices') as [$axis, $value, $amount]) {
                $result = $result->withPrice($axis, $value, Money::of($amount, $currency));
            }

            foreach (self::amounts($pricing, 'supplements') as [$axis, $value, $amount]) {
                $result = $result->withSupplement($axis, $value, Money::of($amount, $currency));
            }
        } catch (MoneyException $moneyException) {
            throw InvalidDefinition::because(\sprintf('template.pricing is not a price: %s', $moneyException->getMessage()));
        }

        return Template::of(
            $root->string('code'),
            Label::of($root->stringMap('name')),
            SkuPattern::parse($root->string('sku_pattern')),
            $result,
            array_map(self::axis(...), $root->readers('axes')),
            array_map(self::face(...), $root->readers('faces')),
            array_map(static fn (mixed $values): Exclusion => Exclusion::of(\is_array($values) ? Combination::condition($values, 'An exclusion') : throw InvalidDefinition::because('template.exclusions lists objects.')), $root->list('exclusions')),
            $root->nullableString('supplier'),
        );
    }

    /**
     * @return list<array{string, string, int}> the axis, the value and the amount of each
     */
    private static function amounts(Reader $pricing, string $key): array
    {
        $amounts = [];

        foreach ($pricing->map($key) as $axis => $values) {
            if (!\is_string($axis)) {
                throw InvalidDefinition::because(\sprintf('template.pricing.%s names its axes by their codes.', $key));
            }

            if (!\is_array($values)) {
                throw InvalidDefinition::because(\sprintf('template.pricing.%s.%s is not an object.', $key, $axis));
            }

            foreach ($values as $value => $amount) {
                $amounts[] = [$axis, (string) $value, \is_int($amount) ? $amount : throw InvalidDefinition::because(\sprintf('template.pricing.%s.%s.%s is not an integer.', $key, $axis, $value))];
            }
        }

        return $amounts;
    }

    private static function axis(Reader $axis): Axis
    {
        return Axis::of($axis->string('code'), Label::of($axis->stringMap('label')), ...array_map(static function (Reader $value): AxisValue {
            $swatch = $value->nullableReader('swatch');

            return AxisValue::of(
                $value->string('code'),
                Label::of($value->stringMap('label')),
                match (true) {
                    !$swatch instanceof Reader => null,
                    null !== $swatch->nullableString('hex') => Swatch::colour($swatch->string('hex')),
                    default => Swatch::photo($swatch->string('photo')),
                },
                $value->nullableString('supplier_name'),
                $value->stringMap('photos'),
                $value->strings('channels'),
            );
        }, $axis->readers('values')));
    }

    private static function face(Reader $face): Face
    {
        return Face::of($face->string('code'), Label::of($face->stringMap('label')), ...array_map(static function (Reader $zone): PrintZone {
            $box = $zone->nullableReader('photo_box');

            return PrintZone::of(
                $zone->string('name'),
                $zone->float('x'),
                $zone->float('y'),
                $zone->float('width'),
                $zone->float('height'),
                $zone->int('dpi'),
                $box instanceof Reader ? PhotoBox::of($box->int('x'), $box->int('y'), $box->int('width'), $box->int('height')) : null,
            );
        }, $face->readers('zones')));
    }
}
