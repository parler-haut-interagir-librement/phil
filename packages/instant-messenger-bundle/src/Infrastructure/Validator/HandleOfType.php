<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Infrastructure\Validator;

use Attribute;
use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\MessengerType;
use Symfony\Component\Validator\Constraint;

/**
 * The value — a `Handle`, or a text detected among the services — is a handle on one of the accepted services. `null`
 * and the empty text are valid, as for every constraint but `NotBlank`.
 *
 * ```php
 * #[HandleOfType([KnownMessenger::Signal, KnownMessenger::WhatsApp])]
 * public ?Handle $messenger = null;
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class HandleOfType extends Constraint
{
    public const string NOT_ACCEPTED_ERROR = '4d8b1e63-9f2a-4c57-b3e0-61a7d9c2f485';

    protected const array ERROR_NAMES = [
        self::NOT_ACCEPTED_ERROR => 'NOT_ACCEPTED_ERROR',
    ];

    public string $message = 'This handle is on none of the accepted services: {{ types }}.';

    /**
     * @param class-string<MessengerType>|MessengerType|EnumSet<covariant MessengerType>|list<class-string<MessengerType>|MessengerType|EnumSet<covariant MessengerType>>|null $types  every international service by default
     * @param list<string>|null                                                                                                                                                $groups
     */
    public function __construct(
        public mixed $types = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
