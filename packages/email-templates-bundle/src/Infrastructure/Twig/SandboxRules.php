<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use DateTimeInterface;
use Twig\Sandbox\SecurityPolicy;

/**
 * The allow-list of the sandbox every template renders in, in Twig's strict mode: what is not listed is refused,
 * `include`, `extends`, `embed`, `import`, `macro`, `block`, `source`, `attribute`, `constant` and `range` among the
 * rest. The application adds to it, through `phil_email_templates.sandbox`, the filters and functions of the Twig
 * extensions it gives the sandbox.
 */
final readonly class SandboxRules
{
    public const array TAGS = ['if', 'for', 'set', 'apply'];

    public const array FILTERS = [
        'abs', 'capitalize', 'date', 'default', 'e', 'escape', 'first', 'format', 'join', 'last', 'length', 'lower',
        'nl2br', 'number_format', 'replace', 'reverse', 'round', 'slice', 'split', 'striptags', 'title', 'trim', 'upper',
        'url_encode', 'panel', 'footer',
    ];

    /** The filters of Twig's Intl extension, allowed when it is installed: dates and numbers in the email's locale. */
    public const array INTL_FILTERS = ['format_date', 'format_datetime', 'format_time', 'format_number', 'format_currency'];

    public const array FUNCTIONS = ['button', 'table', 'image', 'segment', 'max', 'min'];

    public const array TESTS = ['defined', 'empty', 'even', 'odd', 'null', 'none', 'same as', 'divisible by', 'iterable', 'sequence', 'mapping', 'true'];

    public const array METHODS = [DateTimeInterface::class => ['format', 'gettimestamp']];

    /**
     * @param list<string> $filters   filters added to the list
     * @param list<string> $functions functions added to the list
     */
    public static function policy(array $filters = [], array $functions = []): SecurityPolicy
    {
        $policy = new SecurityPolicy(self::TAGS, [...self::FILTERS, ...self::INTL_FILTERS, ...$filters], self::METHODS, [], [...self::FUNCTIONS, ...$functions], self::TESTS);
        $policy->setStrict(true);

        return $policy;
    }
}
