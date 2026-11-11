<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Infrastructure\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The value, an `Address`, is complete and right for its country: each field the country requires, none it does not
 * use, a postal code of its country and region, subdivisions of the country. Each violation is on its field's path.
 * `null` is valid, as for every constraint but `NotNull`.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidAddress extends Constraint
{
    public const string MISSING_ERROR = '1d6b9f3e-4a27-4c58-9e10-73b2c8d5a6f4';

    public const string UNUSED_ERROR = '5e2c7a91-0b4d-4f63-8a1e-d9c3b6f2047a';

    public const string INVALID_POSTAL_CODE_ERROR = 'a8f41c2d-6e3b-4d97-b0a5-12e9c7d4f836';

    public const string UNKNOWN_SUBDIVISION_ERROR = 'c3d9e5a7-2f18-4b6c-91d4-8e0a7b3c5f21';

    /** The message of each violation of `phil/address`, by its value. */
    public const array MESSAGES = [
        'missing' => 'This value is required in an address of this country.',
        'unused' => 'This value is not part of an address of this country.',
        'invalid_postal_code' => 'This postal code is not one of this country or region.',
        'unknown_subdivision' => 'This place is not one of this country.',
    ];

    /** The code of each violation of `phil/address`, by its value. */
    public const array CODES = [
        'missing' => self::MISSING_ERROR,
        'unused' => self::UNUSED_ERROR,
        'invalid_postal_code' => self::INVALID_POSTAL_CODE_ERROR,
        'unknown_subdivision' => self::UNKNOWN_SUBDIVISION_ERROR,
    ];

    protected const array ERROR_NAMES = [
        self::MISSING_ERROR => 'MISSING_ERROR',
        self::UNUSED_ERROR => 'UNUSED_ERROR',
        self::INVALID_POSTAL_CODE_ERROR => 'INVALID_POSTAL_CODE_ERROR',
        self::UNKNOWN_SUBDIVISION_ERROR => 'UNKNOWN_SUBDIVISION_ERROR',
    ];

    /**
     * @param ?list<string> $groups
     */
    public function __construct(?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
    }
}
