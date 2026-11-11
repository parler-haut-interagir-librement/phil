<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Flag;

use Exception;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\Evaluation;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationErrorCode;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagStateSource;
use Phil\FeatureFlagsBundle\Domain\Flag\VariantName;
use Psr\Log\LoggerInterface;
use WeakMap;

/**
 * Evaluates flags **once per request and context**.
 *
 * The context is resolved on every evaluation, and each flag is evaluated once per context and memoized: a
 * flag toggled in the middle of a request does not change its value halfway through it, so a page never
 * renders half of a feature. `reset()` ends the request; a long-running worker calls it between messages.
 *
 * **A context that changes in the middle of a request gets its own evaluations.** A flag read before the
 * firewall has loaded the user (a route condition, an early listener) is evaluated against the anonymous
 * context; once the user is known, the resolver gives the user's context and the flag is evaluated again for
 * it, so an evaluation made before authentication never answers for the authenticated user. The resolver is
 * therefore asked on each call and must stay cheap: it reads the token and the request, and memoizes any
 * lookup it needs. The key of a context is built once per context instance: a resolver that hands back the same
 * instance makes a memoized evaluation a pair of lookups.
 *
 * Providers are asked in order and the first one that knows the flag gives its definition, rules included,
 * so the order is the precedence. A flag no provider knows is off.
 *
 * **The active state and the rules have separate sources.** When a state source is given (the store a toggle
 * writes to), the active state it holds replaces the one of the provider's definition, and the rules stay the
 * provider's: a flag declared with a 20 % rollout, deactivated and then reactivated from the store, gets back
 * its 20 % rollout. A state the source holds for a flag no provider knows is ignored: such a flag has no
 * declared rules and stays off.
 *
 * **An evaluation never throws.** A provider or the state source that fails while answering gives an evaluation that is
 * off, reason `ERROR`: code `PARSE_ERROR` when it throws `InvalidValue` (a definition that no longer reads as one),
 * `GENERAL` for any other exception (a database that cannot be reached). The failure is logged as an error when a
 * logger is given, and memoized like any evaluation, so the request does not ask the failing source again. An
 * `Error` (a bug, not a failure of the source) is not caught.
 *
 * **An experiment's variant is reported once per request and context**, to the exposure recorder when one is
 * given, on the first evaluation of its flag for that context: the moment the application asks is the moment it branches on the answer.
 */
final class FlagEvaluator
{
    /** @var array<string, array<string, Evaluation>> the evaluations by context, then by flag */
    private array $memo = [];

    /** @var array<string, Evaluation> the latest evaluation of each flag, by flag */
    private array $evaluations = [];

    /**
     * The key of each context instance met so far. A context is immutable, so its key never changes; held weakly, an
     * instance the resolver let go is forgotten with it, and no other instance can take its key.
     *
     * @var WeakMap<EvaluationContext, string>
     */
    private WeakMap $keys;

    /**
     * @param list<FlagProvider> $providers
     */
    public function __construct(
        private readonly array $providers,
        private readonly EvaluationContextResolver $contextResolver,
        private readonly ?FlagStateSource $states = null,
        private readonly ?ExposureRecorder $exposures = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->keys = new WeakMap();
    }

    public function isEnabled(FlagName $flag): bool
    {
        return $this->evaluate($flag)->enabled;
    }

    /**
     * The variant of the flag's experiment for the current context, or `null` when the flag is off or has no
     * experiment.
     */
    public function variant(FlagName $flag): ?VariantName
    {
        return $this->evaluate($flag)->variant;
    }

    public function evaluate(FlagName $flag): Evaluation
    {
        $context = $this->contextResolver->current();
        $key = $this->keys[$context] ??= $this->keyOf($context);

        if (!isset($this->memo[$key][$flag->value])) {
            $evaluation = $this->memo[$key][$flag->value] = $this->evaluateUncached($flag, $context);

            if ($evaluation->variant instanceof VariantName) {
                $this->exposures?->record($flag, $evaluation->variant);
            }
        }

        return $this->evaluations[$flag->value] = $this->memo[$key][$flag->value];
    }

    /**
     * The definition of the flag as the first provider that knows it declares it, before any stored state, or
     * `null` when no provider knows it. It reads no context.
     */
    public function definition(FlagName $flag): ?FlagDefinition
    {
        foreach ($this->providers as $provider) {
            $definition = $provider->find($flag);

            if (null !== $definition) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * The flags evaluated during the current request, by name, each with its latest evaluation: what a profiler
     * panel shows. The context they were evaluated against is not kept.
     *
     * @return array<string, Evaluation>
     */
    public function evaluated(): array
    {
        return $this->evaluations;
    }

    public function reset(): void
    {
        $this->memo = [];
        $this->evaluations = [];
    }

    private function evaluateUncached(FlagName $flag, EvaluationContext $context): Evaluation
    {
        try {
            $definition = $this->definition($flag);

            if (!$definition instanceof FlagDefinition) {
                return Evaluation::notFound($flag);
            }

            $active = $this->states?->isActive($flag);
        } catch (InvalidValue $invalidValue) {
            return $this->failed($flag, EvaluationErrorCode::ParseError, $invalidValue);
        } catch (Exception $exception) {
            return $this->failed($flag, EvaluationErrorCode::General, $exception);
        }

        return (null === $active ? $definition : $definition->withActive($active))->evaluate($context);
    }

    private function failed(FlagName $flag, EvaluationErrorCode $code, Exception $exception): Evaluation
    {
        $this->logger?->error('The flag "{flag}" could not be evaluated ({code}): {message}', [
            'flag' => $flag->value,
            'code' => $code->value,
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        return Evaluation::failed($flag, $code);
    }

    /**
     * A key that tells two contexts apart: an absent attribute is empty in it and a present one never is, since a
     * present attribute is never empty.
     */
    private function keyOf(EvaluationContext $context): string
    {
        return \sprintf('%s|%s|%s', $this->part($context->tenant), $this->part($context->project), $this->part($context->subject));
    }

    private function part(?string $value): string
    {
        return rawurlencode($value ?? '');
    }
}
