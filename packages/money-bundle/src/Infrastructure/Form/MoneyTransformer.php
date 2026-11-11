<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Form;

use Override;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

/**
 * Between a `Money` and the values of the `amount` and `currency` fields of `MoneyType`: the amount in the
 * request's locale, the currency by its code. A form without a `currency` field is fixed to its one currency, the
 * default one: what is submitted is in it, even when the form was filled from an amount in another currency. An
 * empty form with the field starts on the default currency.
 *
 * An empty amount is no money (`null`); an amount that cannot be read, finer than its currency counts, or
 * without a currency fails the form with its `invalid_message`. A currency the choice does not offer fails the
 * `currency` field itself.
 *
 * @implements DataTransformerInterface<Money, array{amount?: ?string, currency?: ?string}>
 */
final readonly class MoneyTransformer implements DataTransformerInterface
{
    public function __construct(
        private Currency $defaultCurrency,
        private bool $fixed = false,
    ) {
    }

    /**
     * @param mixed $value the form's data, which the application may have set to anything
     *
     * @return array{amount: string, currency: string}
     */
    #[Override]
    public function transform(mixed $value): array
    {
        if (null !== $value && !$value instanceof Money) {
            throw new UnexpectedTypeException($value, Money::class);
        }

        return [
            'amount' => $value instanceof Money ? LocalizedDecimal::format($value->toDecimal()) : '',
            'currency' => ($value instanceof Money ? $value->currency : $this->defaultCurrency)->code,
        ];
    }

    #[Override]
    public function reverseTransform(mixed $value): ?Money
    {
        $amount = \is_array($value) ? ($value['amount'] ?? null) : null;

        if (!\is_string($amount) || '' === $amount) {
            return null;
        }

        $decimal = LocalizedDecimal::parse($amount);
        $code = $this->fixed ? $this->defaultCurrency->code : ($value['currency'] ?? null);

        if (null === $decimal || !\is_string($code)) {
            throw new TransformationFailedException(\sprintf('"%s" is not an amount in a currency.', $amount));
        }

        try {
            return Money::ofDecimal($decimal, Currency::of($code));
        } catch (MoneyException $moneyException) {
            throw new TransformationFailedException($moneyException->getMessage(), $moneyException->getCode(), $moneyException);
        }
    }
}
