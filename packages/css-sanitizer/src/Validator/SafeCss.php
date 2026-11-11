<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Validator;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The value, a string, is CSS the sanitizer keeps whole: nothing in it is removed. Each removal is a violation, whose
 * parameters name what was removed, why, and on which line; past the first `Sanitized::MAX_REMOVALS`, one more
 * violation, `moreMessage`, counts the others. `null` and the empty string are valid, as for every constraint but
 * `NotBlank`.
 *
 * `declarations` checks the content of a `style` attribute rather than a style sheet. The policy is the one of the
 * `CssSanitizer` the validator is built with — the default policy, unless the application registers
 * `SafeCssValidator` as a service with its own sanitizer.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class SafeCss extends Constraint
{
    public const string NOT_ALLOWED_ERROR = '6b0f3c2e-9d41-4a7e-b5c8-2e17d9a04f63';

    protected const array ERROR_NAMES = [
        self::NOT_ALLOWED_ERROR => 'NOT_ALLOWED_ERROR',
    ];

    /**
     * @param ?list<string> $groups
     */
    public function __construct(
        public bool $declarations = false,
        public string $message = 'This CSS holds {{ removed }} on line {{ line }}: {{ subject }}.',
        public string $moreMessage = 'This CSS holds {{ count }} more parts that are not allowed.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
