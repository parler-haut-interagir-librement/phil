<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The state may move to its new case: the previous case declares it with `#[TransitionsTo]`. The previous case is
 * the one `from` names on the object — a DTO keeps it —, or else the one Doctrine loaded for a managed entity. A new
 * object, without a previous case, starts in an initial case unless `fromInitial` is `false`; `null` is valid, as for
 * every constraint but `NotNull`.
 *
 * ```php
 * #[ORM\Column(enumType: Delivery::class)]
 * #[Transition]
 * public Delivery $status = Delivery::Ordered;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class Transition extends Constraint
{
    public const string NOT_ALLOWED_ERROR = 'c3f1a7d2-5e84-4b19-a0c6-8d2e9f4b7a15';

    public const string NOT_INITIAL_ERROR = '9a2e6c41-0b7d-4f3a-b8e5-1c4d7f0a2e96';

    protected const array ERROR_NAMES = [
        self::NOT_ALLOWED_ERROR => 'NOT_ALLOWED_ERROR',
        self::NOT_INITIAL_ERROR => 'NOT_INITIAL_ERROR',
    ];

    public string $message = 'The state cannot move from {{ from }} to {{ to }}.';

    public string $initialMessage = 'The state cannot start as {{ to }}.';

    /**
     * @param string|null       $from   the property of the object that holds the previous case
     * @param list<string>|null $groups the validation groups
     */
    public function __construct(
        public readonly ?string $from = null,
        public readonly bool $fromInitial = true,
        ?string $message = null,
        ?string $initialMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->initialMessage = $initialMessage ?? $this->initialMessage;
    }
}
