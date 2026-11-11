<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * An A/B or multivariate test: the variants of a flag and their weights, split on one attribute of the context.
 *
 * **A stable assignment.** A targeting key falls into bucket `murmur3a("<flag>:variant:<key>") mod 100`
 * ({@see Bucket::forVariant()}) and the buckets are handed to the variants in their declared order, by
 * cumulative weight: with `control: 50, treatment: 50`, buckets 0 to 49 see `control`. The same tenant sees the
 * same variant on every request and every server. **Change the weights only between experiments**: moving the
 * boundary moves contexts from one arm to the other in the middle of a measurement.
 *
 * A context without the attribute is not placed: the flag is then off for it, as for a rule that cannot read
 * its attribute.
 */
final readonly class Experiment
{
    /**
     * @param list<Variant> $variants
     */
    private function __construct(
        public TargetingAttribute $attribute,
        public array $variants,
    ) {
    }

    /**
     * @param list<Variant> $variants
     */
    public static function of(TargetingAttribute $attribute, array $variants): self
    {
        if (\count($variants) < 2) {
            throw InvalidValue::because('An experiment compares at least two variants.');
        }

        $declared = [];
        $total = 0;

        foreach ($variants as $variant) {
            if (isset($declared[$variant->name->value])) {
                throw InvalidValue::because(\sprintf('The variant "%s" is declared twice.', $variant->name->value));
            }

            $declared[$variant->name->value] = $variant;
            $total += $variant->weight;
        }

        if (100 !== $total) {
            throw InvalidValue::because(\sprintf('The weights of the variants of an experiment add up to 100; these add up to %d.', $total));
        }

        return new self($attribute, $variants);
    }

    public function assign(FlagName $flag, EvaluationContext $context): ?VariantName
    {
        $value = $context->get($this->attribute);

        if (null === $value) {
            return null;
        }

        $bucket = Bucket::forVariant($flag, $value)->value;
        $boundary = 0;

        foreach ($this->variants as $variant) {
            $boundary += $variant->weight;

            if ($bucket < $boundary) {
                return $variant->name;
            }
        }

        // Unreachable: the weights add up to 100 and a bucket is below 100.
        throw InvalidValue::because('The weights of an experiment no longer cover every bucket.');
    }
}
