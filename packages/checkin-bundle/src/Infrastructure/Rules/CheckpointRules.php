<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\Rules;

use DateTimeZone;
use ErrorException;
use Phil\Checkin\CheckpointId;
use Phil\Checkin\Credential;
use Phil\CheckinBundle\Application\Contract\AdmissionRules;
use Phil\CheckinBundle\Application\Contract\CredentialAttributes;
use Psr\Clock\ClockInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Throwable;

/**
 * Evaluates the admission rule of a checkpoint on a credential with Symfony's ExpressionLanguage. A rule reads four
 * variables:
 *
 * - `credential`, the `Credential` of `phil/checkin`: `credential.id`, `credential.label`, `credential.maxEntries`,
 *   `credential.validFrom`…;
 * - `attributes`, what the application's `CredentialAttributes` answers for it;
 * - `checkpoint`, the name of the checkpoint;
 * - `now`, the instant of the clock in the rules' time zone: `now.format('H') < '22'`, `now.format('N') <= 5`.
 *
 * A credential is admitted only when the rule evaluates to `true` itself; a rule longer than {@see self::MAX_LENGTH}
 * bytes, one that does not parse, or one that fails on a credential refuses it — a door closes rather than opens
 * on a mistake. The parsed rules are cached by the expression language, one entry per expression: the service is
 * safe to share across the requests of a long-running worker.
 */
final readonly class CheckpointRules
{
    public const int MAX_LENGTH = 1_000;

    /** The variables a rule may read. */
    public const array NAMES = ['credential', 'attributes', 'checkpoint', 'now'];

    private ExpressionLanguage $language;

    public function __construct(
        private AdmissionRules $rules,
        private ClockInterface $clock,
        private CredentialAttributes $attributes = new NoCredentialAttributes(),
        private ?DateTimeZone $timezone = null,
        ?ExpressionLanguage $language = null,
    ) {
        $this->language = $language ?? new ExpressionLanguage();
    }

    /**
     * The syntax error of a rule — too long, not an expression, or reading a variable a rule does not have — or null
     * for a rule that parses.
     */
    public static function lint(string $rule, ?ExpressionLanguage $language = null): ?string
    {
        if (\strlen($rule) > self::MAX_LENGTH) {
            return \sprintf('A rule has at most %d bytes.', self::MAX_LENGTH);
        }

        try {
            ($language ?? new ExpressionLanguage())->lint($rule, self::NAMES);
        } catch (SyntaxError $syntaxError) {
            return $syntaxError->getMessage();
        }

        return null;
    }

    /**
     * Whether a checkpoint has a rule at all: one without admits on the policy alone.
     */
    public function has(CheckpointId $checkpoint): bool
    {
        return null !== $this->rules->ruleFor($checkpoint);
    }

    /**
     * Whether a credential meets the rule of a checkpoint; always, for a checkpoint without one.
     */
    public function admits(CheckpointId $checkpoint, Credential $credential): bool
    {
        $rule = $this->rules->ruleFor($checkpoint);

        if (null === $rule) {
            return true;
        }

        if (\strlen($rule) > self::MAX_LENGTH) {
            return false;
        }

        $now = $this->clock->now();

        // A warning — an attribute the rule reads and the application did not give — refuses like an error.
        set_error_handler(static fn (int $level, string $message): never => throw new ErrorException($message, 0, $level));

        try {
            return true === $this->language->evaluate($rule, [
                'credential' => $credential,
                'attributes' => $this->attributes->of($credential),
                'checkpoint' => $checkpoint->value,
                'now' => $this->timezone instanceof DateTimeZone ? $now->setTimezone($this->timezone) : $now,
            ]);
        } catch (Throwable) {
            return false;
        } finally {
            restore_error_handler();
        }
    }
}
