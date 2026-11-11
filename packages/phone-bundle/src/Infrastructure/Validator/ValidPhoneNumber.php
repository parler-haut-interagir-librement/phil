<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Infrastructure\Validator;

use Attribute;
use Phil\Phone\PhoneNumberType;
use Symfony\Component\Validator\Constraint;

/**
 * The value — a text or a `PhoneNumber` — is a number of its country's numbering plan, of an accepted type and from
 * an accepted country when the constraint names them. A text without its country code is read in `region`, the
 * bundle's `default_region` by default. `null` and the empty text are valid, as for every constraint but `NotBlank`.
 *
 * ```php
 * #[ValidPhoneNumber(types: [PhoneNumberType::Mobile, PhoneNumberType::FixedLineOrMobile])]
 * public ?PhoneNumber $mobile = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidPhoneNumber extends Constraint
{
    public const string INVALID_ERROR = '3f7c2a91-5d4e-4b8a-9c16-e0a2d7b45f83';

    public const string TYPE_ERROR = '8b1e4d6a-2c9f-4a73-b5e0-91d3f6c27a48';

    public const string REGION_ERROR = 'c4a92e17-6b3d-4f05-8e1a-5d7b0c9f2e64';

    protected const array ERROR_NAMES = [
        self::INVALID_ERROR => 'INVALID_ERROR',
        self::TYPE_ERROR => 'TYPE_ERROR',
        self::REGION_ERROR => 'REGION_ERROR',
    ];

    public string $message = 'This value is not a valid phone number.';

    public string $typeMessage = 'This phone number is not of an accepted type.';

    public string $regionMessage = 'This phone number is not from an accepted country: {{ regions }}.';

    /**
     * @param ?list<PhoneNumberType> $types   every type by default
     * @param ?list<string>          $regions every country by default, by their ISO 3166-1 alpha-2 codes
     * @param ?list<string>          $groups
     */
    public function __construct(
        public ?string $region = null,
        public ?array $types = null,
        public ?array $regions = null,
        ?string $message = null,
        ?string $typeMessage = null,
        ?string $regionMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->typeMessage = $typeMessage ?? $this->typeMessage;
        $this->regionMessage = $regionMessage ?? $this->regionMessage;
    }
}
