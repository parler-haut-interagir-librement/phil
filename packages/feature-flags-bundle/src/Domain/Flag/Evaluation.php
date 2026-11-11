<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The outcome of one flag for one context, with its reason, its error code when the reason is `ERROR`, and the
 * variant of its experiment when the flag has one and is on.
 */
final readonly class Evaluation
{
    public function __construct(
        public FlagName $flag,
        public bool $enabled,
        public EvaluationReason $reason,
        public ?EvaluationErrorCode $errorCode = null,
        public ?VariantName $variant = null,
    ) {
        if ((EvaluationReason::Error === $reason) !== ($errorCode instanceof EvaluationErrorCode)) {
            throw InvalidValue::because('An evaluation carries an error code exactly when its reason is ERROR.');
        }

        if (EvaluationReason::Error === $reason && $enabled) {
            throw InvalidValue::because('An evaluation that could not be made is off.');
        }

        if ($variant instanceof VariantName && !$enabled) {
            throw InvalidValue::because('Only an evaluation that is on carries a variant.');
        }
    }

    public static function notFound(FlagName $flag): self
    {
        return self::failed($flag, EvaluationErrorCode::FlagNotFound);
    }

    /**
     * An evaluation that could not be made, for the reason the code gives: off, reason `ERROR`.
     */
    public static function failed(FlagName $flag, EvaluationErrorCode $errorCode): self
    {
        return new self($flag, false, EvaluationReason::Error, $errorCode);
    }
}
