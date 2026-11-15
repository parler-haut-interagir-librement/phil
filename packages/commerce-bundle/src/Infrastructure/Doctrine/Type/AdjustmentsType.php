<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use JsonException;
use Override;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;

/**
 * The adjustments of an order in one JSON column: a list of objects, each with its type, label, amount in minor
 * units, currency, the line it is on (`type`, `id` and the `configuration` fingerprint of a configured line, or
 * `null`), whether it is neutral, and its origin (or `null`; a row without the key has none).
 *
 * Every adjustment is rebuilt through its own constructors on the way out; a stored value that is not one is
 * refused as a DBAL conversion error.
 */
final class AdjustmentsType extends Type
{
    public const string NAME = 'phil_commerce_adjustments';

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

        $adjustments = [];

        foreach ($value as $adjustment) {
            if (!$adjustment instanceof Adjustment) {
                throw InvalidType::new($adjustment, self::class, [Adjustment::class]);
            }

            $adjustments[] = [
                'type' => $adjustment->type->value,
                'label' => $adjustment->label,
                'amount' => $adjustment->amount->minorUnits,
                'currency' => $adjustment->amount->currency->code,
                'line' => $adjustment->line instanceof SellableReference ? $this->line($adjustment->line) : null,
                'neutral' => $adjustment->neutral,
                'origin' => $adjustment->origin,
            ];
        }

        return json_encode($adjustments, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return list<Adjustment>
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
            throw ValueNotConvertible::new($value, self::class, 'a JSON list of adjustments');
        }

        $adjustments = [];

        foreach ($rows as $row) {
            $line = \is_array($row) ? ($row['line'] ?? null) : null;
            $origin = \is_array($row) ? ($row['origin'] ?? null) : null;
            $fingerprint = \is_array($line) ? ($line['configuration'] ?? null) : null;
            $type = \is_array($row) && \is_string($row['type'] ?? null) ? AdjustmentType::tryFrom($row['type']) : null;

            if (!\is_array($row) || !$type instanceof AdjustmentType
                || !\is_string($row['label'] ?? null) || !\is_int($row['amount'] ?? null) || !\is_string($row['currency'] ?? null)
                || !\is_bool($row['neutral'] ?? null) || (null !== $origin && !\is_string($origin))
                || (null !== $line && (!\is_array($line) || !\is_string($line['type'] ?? null) || !\is_string($line['id'] ?? null)))
                || (null !== $fingerprint && !\is_string($fingerprint))) {
                throw ValueNotConvertible::new($value, self::class, 'adjustments with a known type, label, amount, currency, line, neutral flag and origin');
            }

            try {
                $adjustments[] = Adjustment::of(
                    $type,
                    $row['label'],
                    Money::of($row['amount'], Currency::of($row['currency'])),
                    null === $line ? null : SellableReference::of($line['type'], $line['id'], $fingerprint),
                    $row['neutral'],
                    $origin,
                );
            } catch (DomainError|MoneyException $invalid) {
                throw ValueNotConvertible::new($value, self::class, null, $invalid);
            }
        }

        return $adjustments;
    }

    /**
     * @return array{type: string, id: string, configuration?: string}
     */
    private function line(SellableReference $line): array
    {
        $row = ['type' => $line->type, 'id' => $line->id];

        if (null !== $line->configuration) {
            $row['configuration'] = $line->configuration;
        }

        return $row;
    }
}
