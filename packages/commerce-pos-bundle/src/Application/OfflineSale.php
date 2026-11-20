<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use DateTimeImmutable;
use Exception;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * A sale a register rang up offline, as it sends it once back online:
 *
 *     {"id": "0192f3c4-…", "session": "0192f3a0-…", "by": "cashier-7", "rung_at": "2026-11-03T14:05:00-05:00",
 *      "total": 2415, "tip": 200,
 *      "lines": [{"type": "product", "id": "TEA-1", "quantity": 2}],
 *      "payments": [{"method": "cash", "amount": 2415},
 *                   {"method": "debit", "amount": 0, "approval": "A1B2C3"}]}
 *
 * The id, chosen by the register, becomes the order's number and makes a second import harmless. `rung_at` is an
 * RFC 3339 moment with its offset or `Z`, seconds required and a fraction allowed — what JavaScript's
 * `Date.prototype.toISOString()` gives. Amounts are in minor units of the register's currency, 0 to 10^12;
 * `total` is what the register charged, to be compared with the server's. A line holds 1 to 100000 units. The change
 * was given at the register: what was tendered is not sent.
 */
final readonly class OfflineSale
{
    /** The highest amount a sale carries, in minor units: far above any sale, far below an overflow. */
    public const int MAX_AMOUNT = 1_000_000_000_000;

    /** The most units a line holds. */
    public const int MAX_QUANTITY = 100_000;

    private const string MOMENT = '/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D';

    /**
     * @param list<array{type: string, id: string, quantity: int}>        $lines
     * @param list<array{method: string, amount: int, approval: ?string}> $payments
     */
    private function __construct(
        public string $id,
        public string $session,
        public string $by,
        public DateTimeImmutable $rungAt,
        public int $total,
        public int $tip,
        public array $lines,
        public array $payments,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $id = self::string($data, 'id');

        if (1 !== preg_match('/^[\x21-\x7E]{1,64}$/D', $id)) {
            throw InvalidValue::because("An offline sale's id is 1 to 64 visible ASCII characters.");
        }

        $rungAt = self::moment(self::string($data, 'rung_at'));
        $tip = $data['tip'] ?? 0;

        if (!\is_int($tip) || $tip < 0 || $tip > self::MAX_AMOUNT) {
            throw InvalidValue::because('An offline sale\'s "tip" is 0 to 1000000000000.');
        }

        $total = self::int($data, 'total');

        if ($total < 0 || $total > self::MAX_AMOUNT) {
            throw InvalidValue::because('An offline sale\'s "total" is 0 to 1000000000000.');
        }

        $lines = [];

        foreach (self::list($data, 'lines') as $line) {
            $quantity = self::int($line, 'quantity');

            if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
                throw InvalidValue::because("An offline sale's line holds 1 to 100000 units.");
            }

            $lines[] = ['type' => self::string($line, 'type'), 'id' => self::string($line, 'id'), 'quantity' => $quantity];
        }

        if ([] === $lines) {
            throw InvalidValue::because('An offline sale has lines.');
        }

        $payments = [];

        foreach (\array_key_exists('payments', $data) ? self::list($data, 'payments') : [] as $payment) {
            $amount = self::int($payment, 'amount');
            $approval = $payment['approval'] ?? null;

            if ($amount < 0 || $amount > self::MAX_AMOUNT || (null !== $approval && !\is_string($approval))) {
                throw InvalidValue::because('An offline sale\'s payment has an amount of 0 to 1000000000000, and a text "approval".');
            }

            $payments[] = ['method' => self::string($payment, 'method'), 'amount' => $amount, 'approval' => $approval];
        }

        return new self($id, self::string($data, 'session'), self::string($data, 'by'), $rungAt, $total, $tip, $lines, $payments);
    }

    /**
     * Reads an RFC 3339 moment, and refuses one that names no real date or time — the 30th of February, 24:00 — which
     * PHP would otherwise roll over into the next.
     */
    private static function moment(string $value): DateTimeImmutable
    {
        if (1 === preg_match(self::MOMENT, $value, $parts)) {
            try {
                $moment = new DateTimeImmutable($value);
            } catch (Exception) {
                $moment = null;
            }

            if ($moment instanceof DateTimeImmutable && $moment->format('Y-m-d\TH:i:s') === $parts[1]) {
                return $moment;
            }
        }

        throw InvalidValue::because('An offline sale\'s "rung_at" is an RFC 3339 moment: 2026-11-03T14:05:00-05:00, or 2026-11-03T19:05:00.000Z.');
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : throw InvalidValue::because(\sprintf('An offline sale\'s "%s" is a text.', $key));
    }

    /**
     * @param array<mixed> $data
     */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return \is_int($value) ? $value : throw InvalidValue::because(\sprintf('An offline sale\'s "%s" is a whole number.', $key));
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>>
     */
    private static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidValue::because(\sprintf('An offline sale\'s "%s" is a list.', $key));
        }

        $items = [];

        foreach ($value as $item) {
            $items[] = \is_array($item) ? $item : throw InvalidValue::because(\sprintf('An offline sale\'s "%s" holds objects.', $key));
        }

        return $items;
    }
}
