<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Generator;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Internal\Check;

/**
 * A product template: a blank a design is made on — a shirt cut, a mug, a poster — with its axes and their values,
 * its faces and their print zones, the price of each combination, the combinations it does not make, and the pattern
 * of the SKU of each product.
 *
 * A template knows nothing of what it is: a shirt has a size and a colour axis, a poster a format and a paper.
 */
final readonly class Template
{
    /** The most axes a template has. */
    public const int MAX_AXES = 8;

    /** The most combinations its axes give, exclusions not counted. */
    public const int MAX_COMBINATIONS = 10_000;

    /**
     * @param non-empty-array<string, Axis> $axes       by code, in order
     * @param array<string, Face>           $faces      by code, in order
     * @param list<Exclusion>               $exclusions
     */
    private function __construct(
        public string $code,
        public Label $name,
        public SkuPattern $skuPattern,
        public Pricing $pricing,
        private array $axes,
        private array $faces,
        public array $exclusions,
        public ?string $supplier,
    ) {
    }

    /**
     * @param string          $code       1 to 32 letters, digits, ".", "_" or "-", starting with a letter or digit;
     *                                    `{template}` in a SKU pattern
     * @param list<Axis>      $axes       1 to 8, in the order the SKU and the selectors read them
     * @param list<Face>      $faces      the faces that take a print
     * @param list<Exclusion> $exclusions the combinations it does not make
     * @param string|null     $supplier   the blank, as its supplier names it: a brand and a model
     */
    public static function of(string $code, Label $name, SkuPattern $skuPattern, Pricing $pricing, array $axes, array $faces = [], array $exclusions = [], ?string $supplier = null): self
    {
        Check::skuCode($code, 'a template', 32);

        if (null !== $supplier) {
            Check::text($supplier, \sprintf('The supplier of the template %s', $code));
        }

        $keyedAxes = self::keyedAxes($code, $axes);
        $keyedFaces = self::keyedFaces($code, $faces);

        foreach ($keyedAxes as $axis) {
            foreach ($axis->values() as $value) {
                $value->checkFaces(array_keys($keyedFaces));
            }
        }

        foreach ($skuPattern->placeholders() as $placeholder) {
            if (!isset($keyedAxes[$placeholder]) && !\in_array($placeholder, Axis::RESERVED, true)) {
                throw InvalidDefinition::because(\sprintf('The SKU pattern of the template %s names {%s}, which is not one of its axes.', $code, $placeholder));
            }
        }

        $pricing->checkAxes($keyedAxes);

        foreach ($exclusions as $exclusion) {
            foreach ($exclusion->values as $axis => $values) {
                foreach ($values as $value) {
                    if (!isset($keyedAxes[$axis]) || !$keyedAxes[$axis]->has($value)) {
                        throw InvalidDefinition::because(\sprintf('An exclusion of the template %s names %s %s, which it does not have.', $code, $axis, $value));
                    }
                }
            }
        }

        return new self($code, $name, $skuPattern, $pricing, $keyedAxes, $keyedFaces, $exclusions, $supplier);
    }

    /**
     * @return non-empty-list<Axis> in order
     */
    public function axes(): array
    {
        return array_values($this->axes);
    }

    public function axis(string $code): ?Axis
    {
        return $this->axes[$code] ?? null;
    }

    /**
     * @return list<Face> in order
     */
    public function faces(): array
    {
        return array_values($this->faces);
    }

    public function face(string $code): ?Face
    {
        return $this->faces[$code] ?? null;
    }

    /**
     * Whether the template makes the combination: no exclusion excludes it.
     */
    public function makes(Combination $combination): bool
    {
        return array_all($this->exclusions, static fn (Exclusion $exclusion): bool => !$exclusion->excludes($combination));
    }

    /**
     * Every combination the template makes, the last axis varying fastest.
     *
     * @return Generator<int, Combination>
     */
    public function combinations(): Generator
    {
        $axes = $this->axes();
        $positions = array_fill(0, \count($axes), 0);
        $values = array_map(static fn (Axis $axis): array => $axis->values(), $axes);

        while (true) {
            $combination = [];

            foreach ($axes as $index => $axis) {
                $combination[$axis->code] = $values[$index][$positions[$index]];
            }

            $combination = new Combination($combination);

            if ($this->makes($combination)) {
                yield $combination;
            }

            for ($index = \count($axes) - 1; $index >= 0; --$index) {
                if (++$positions[$index] < \count($values[$index])) {
                    continue 2;
                }

                $positions[$index] = 0;
            }

            return;
        }
    }

    /**
     * @param list<Axis> $axes
     *
     * @return non-empty-array<string, Axis>
     */
    private static function keyedAxes(string $code, array $axes): array
    {
        if ([] === $axes || \count($axes) > self::MAX_AXES) {
            throw InvalidDefinition::because(\sprintf('The template %s has 1 to %d axes.', $code, self::MAX_AXES));
        }

        $keyed = [];
        $combinations = 1;

        foreach ($axes as $axis) {
            if (isset($keyed[$axis->code])) {
                throw InvalidDefinition::because(\sprintf('The template %s lists the axis %s twice.', $code, $axis->code));
            }

            $keyed[$axis->code] = $axis;
            $combinations *= \count($axis->values());
        }

        if ($combinations > self::MAX_COMBINATIONS) {
            throw InvalidDefinition::because(\sprintf('The axes of the template %s give %d combinations, more than %d.', $code, $combinations, self::MAX_COMBINATIONS));
        }

        return $keyed;
    }

    /**
     * @param list<Face> $faces
     *
     * @return array<string, Face>
     */
    private static function keyedFaces(string $code, array $faces): array
    {
        $keyed = [];

        foreach ($faces as $face) {
            if (isset($keyed[$face->code])) {
                throw InvalidDefinition::because(\sprintf('The template %s lists the face %s twice.', $code, $face->code));
            }

            $keyed[$face->code] = $face;
        }

        return $keyed;
    }
}
