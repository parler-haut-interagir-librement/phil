<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * One arm of an experiment and its share of the contexts, a whole percentage from 0 to 100. A share of 0 keeps a
 * variant declared while sending it nobody, which is how an arm is closed without reshuffling the others.
 */
final readonly class Variant
{
    private function __construct(
        public VariantName $name,
        public int $weight,
    ) {
    }

    public static function of(VariantName $name, int $weight): self
    {
        if ($weight < 0 || $weight > 100) {
            throw InvalidValue::because(\sprintf('The weight of the variant "%s" is between 0 and 100; got %d.', $name->value, $weight));
        }

        return new self($name, $weight);
    }
}
