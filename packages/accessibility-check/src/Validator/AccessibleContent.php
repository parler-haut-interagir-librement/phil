<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Validator;

use Attribute;
use Phil\AccessibilityCheck\Severity;
use Symfony\Component\Validator\Constraint;

/**
 * The value — HTML in a string, or a UJO `Document` — is accessible: the checker finds nothing `$minSeverity` or
 * graver. Each such finding is a violation, whose message is the finding's, written in `$locale` or the validator's
 * locale, with its rule, success criterion, severity and location as parameters; a content the checker refuses — too
 * large, not UTF-8 — is one violation. `null` and the empty string are valid, as for every constraint but `NotBlank`.
 *
 * The policy and the rules are those of the `AccessibilityChecker` the validator is built with — the defaults, unless
 * the application registers `AccessibleContentValidator` as a service with its own checker.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class AccessibleContent extends Constraint
{
    public const string INACCESSIBLE_ERROR = '6f1d2c84-93b7-4a5e-8e20-5c4b7d19a3f6';

    public const string REFUSED_ERROR = 'c27e9b40-5d18-4f63-a1c9-08e6f4b2d75a';

    protected const array ERROR_NAMES = [
        self::INACCESSIBLE_ERROR => 'INACCESSIBLE_ERROR',
        self::REFUSED_ERROR => 'REFUSED_ERROR',
    ];

    /**
     * @param ?string       $language the language of a content that does not say its own; the checker's policy's when
     *                                `null`
     * @param ?string       $locale   the locale of the messages; the validator's when `null`
     * @param ?list<string> $groups
     */
    public function __construct(
        public Severity $minSeverity = Severity::Error,
        public ?string $language = null,
        public ?string $locale = null,
        public string $message = '{{ message }}',
        public string $refusedMessage = 'This content cannot be checked for accessibility: {{ reason }}',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
