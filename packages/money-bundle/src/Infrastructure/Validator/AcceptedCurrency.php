<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The value — a `Money`, a `Currency` or a currency code — is in a currency the application accepts
 * (`phil_money.currencies`). `null` is valid, as for every constraint but `NotNull`.
 *
 * ```php
 * #[AcceptedCurrency]
 * public ?Money $price = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AcceptedCurrency extends Constraint
{
    public const string NOT_ACCEPTED_ERROR = '3f9b2c71-5d4e-4a8f-b0c6-8e1d7a2f9c54';

    protected const array ERROR_NAMES = [
        self::NOT_ACCEPTED_ERROR => 'NOT_ACCEPTED_ERROR',
    ];

    public string $message = 'The currency {{ currency }} is not accepted.';

    /**
     * @param list<string>|null $groups the validation groups
     */
    public function __construct(?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
