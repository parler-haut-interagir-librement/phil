<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Infrastructure\Validator;

use Attribute;
use Phil\Enums\Collection\EnumSet;
use Phil\Url\UrlType;
use Symfony\Component\Validator\Constraint;

/**
 * The value — a text, a `Url` or a `TypedUrl` — is a web address of one of the accepted kinds. `null` and the empty
 * text are valid, as for every constraint but `NotBlank`.
 *
 * ```php
 * #[UrlOfType([KnownUrlType::Facebook, KnownUrlType::Instagram])]
 * public ?string $social = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class UrlOfType extends Constraint
{
    public const string INVALID_ERROR = '6c1e9b42-3a7d-4f85-9e20-b4d8c7a51f36';

    public const string NOT_ACCEPTED_ERROR = '9a4f2d17-8c3b-4e6a-a1d5-27f9e0b6c843';

    protected const array ERROR_NAMES = [
        self::INVALID_ERROR => 'INVALID_ERROR',
        self::NOT_ACCEPTED_ERROR => 'NOT_ACCEPTED_ERROR',
    ];

    public string $message = 'This value is not a valid web address.';

    public string $typeMessage = 'This address is not one of the accepted kinds: {{ types }}.';

    /**
     * @param class-string<UrlType>|UrlType|EnumSet<covariant UrlType>|list<class-string<UrlType>|UrlType|EnumSet<covariant UrlType>>|null $types  every international kind by default
     * @param list<string>|null                                                                                                            $groups
     */
    public function __construct(
        public mixed $types = null,
        ?string $message = null,
        ?string $typeMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->typeMessage = $typeMessage ?? $this->typeMessage;
    }
}
