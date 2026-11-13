<?php

declare(strict_types=1);

namespace Phil\Automations\Expression;

use ErrorException;
use Override;
use Phil\Automations\Contract\ConditionEvaluator;
use Phil\Automations\Exception\ActionFailed;
use Phil\Automations\Exception\InvalidDefinition;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Throwable;

/**
 * Conditions in the language `expression`: Symfony's ExpressionLanguage, without any function, over three variables
 * that hold arrays and strings only — `payload`, `outputs` and `subject` —, so that a condition can call no method and
 * read nothing else.
 *
 * ```
 * payload['plan'] == 'pro' and (outputs['subscribe']['created'] ?? false)
 * ```
 *
 * The parsed conditions are kept in a cache of 256 entries, so that a worker that runs for days keeps a bounded
 * memory. A condition gives `true` or `false`. One that gives another value, or that fails while it is evaluated — a
 * key read without `??` that is missing, a type that does not compare —, throws a permanent `ActionFailed`: the run
 * fails, and its steps are compensated.
 */
final readonly class ExpressionConditions implements ConditionEvaluator
{
    public const string LANGUAGE = 'expression';

    private const array NAMES = ['payload', 'outputs', 'subject'];

    private ExpressionLanguage $language;

    public function __construct(?ExpressionLanguage $language = null)
    {
        $this->language = $language ?? new SafeExpressionLanguage(new ArrayAdapter(0, false, 0, 256));
    }

    #[Override]
    public function language(): string
    {
        return self::LANGUAGE;
    }

    #[Override]
    public function check(string $source): void
    {
        try {
            $this->language->lint($source, self::NAMES);
        } catch (SyntaxError $syntaxError) {
            throw InvalidDefinition::because(\sprintf('The condition "%s" does not read: %s', $source, $syntaxError->getMessage()));
        }
    }

    #[Override]
    public function evaluate(string $source, array $variables): bool
    {
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });

        try {
            $result = $this->language->evaluate($source, $variables);
        } catch (Throwable $throwable) {
            throw ActionFailed::permanent(\sprintf('The condition "%s" failed: %s', $source, $throwable->getMessage()), $throwable);
        } finally {
            restore_error_handler();
        }

        if (!\is_bool($result)) {
            throw ActionFailed::permanent(\sprintf('The condition "%s" gives a %s, not true or false.', $source, get_debug_type($result)));
        }

        return $result;
    }
}
