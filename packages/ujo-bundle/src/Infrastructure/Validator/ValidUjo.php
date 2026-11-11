<?php

declare(strict_types=1);

namespace Phil\UjoBundle\Infrastructure\Validator;

use Attribute;
use Phil\Ujo\Validation;
use Symfony\Component\Validator\Constraint;

/**
 * The value — UJO JSON or a `Document` — is a valid document under the bundle's configuration at the constraint's
 * `level`, its shape by default, and, when the constraint names types, holds only blocks of those types, nested ones
 * included. `null` and the empty text are valid, as for every constraint but `NotBlank`.
 *
 * ```php
 * #[ValidUjo(types: ['paragraph', 'header', 'list', 'quote'])]
 * #[ValidUjo(level: Validation::Publication, groups: ['publication'])]
 * public ?string $body = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class ValidUjo extends Constraint
{
    public const string INVALID_ERROR = '6f0e2c8a-3b1d-4e7f-9a25-c4d81b7e0f39';

    public const string TYPE_ERROR = 'b2d94e1f-7c3a-4058-8e6b-1a9f3c5d7e20';

    protected const array ERROR_NAMES = [
        self::INVALID_ERROR => 'INVALID_ERROR',
        self::TYPE_ERROR => 'TYPE_ERROR',
    ];

    public string $message = 'This value is not a valid UJO document: {{ error }}';

    public string $typeMessage = 'Blocks of type {{ type }} are not accepted here.';

    /**
     * @param ?list<string> $types  the canonical block types accepted, every registered one by default
     * @param ?list<string> $groups
     * @param Validation    $level  how much of the document is checked: its shape, or what it needs to be published
     */
    public function __construct(
        public ?array $types = null,
        ?string $message = null,
        ?string $typeMessage = null,
        ?array $groups = null,
        mixed $payload = null,
        public Validation $level = Validation::Shape,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->typeMessage = $typeMessage ?? $this->typeMessage;
    }
}
