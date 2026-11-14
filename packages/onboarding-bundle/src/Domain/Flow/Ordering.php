<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Flow;

use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;

/**
 * The order of the steps of a flow, declared by several packages: each step keeps its place of declaration, unless it
 * asks to come before or after others; the steps a step waits for are then placed right before it, in their order of
 * declaration. A step that is not declared is passed over, so that a package can place a step next to one of a package
 * that may not be installed; steps that ask to come before one another in a circle are refused.
 */
final class Ordering
{
    /**
     * @param list<StepDefinition> $steps in the order of declaration, each identifier once
     *
     * @return list<StepDefinition> the steps in their order
     *
     * @throws InvalidFlow when steps ask to come before one another in a circle
     */
    public static function sort(array $steps): array
    {
        /** @var array<string, StepDefinition> $declared */
        $declared = [];
        /** @var array<string, array<string, string>> $waitsFor the steps that come before each one */
        $waitsFor = [];

        foreach ($steps as $step) {
            $declared[$step->id] = $step;
            $waitsFor[$step->id] = [];
        }

        foreach ($steps as $step) {
            foreach ($step->after as $other) {
                if (isset($declared[$other])) {
                    $waitsFor[$step->id][$other] = $other;
                }
            }

            foreach ($step->before as $other) {
                if (isset($declared[$other])) {
                    $waitsFor[$other][$step->id] = $step->id;
                }
            }
        }

        /** @var array<string, StepDefinition> $sorted */
        $sorted = [];

        foreach (array_keys($declared) as $id) {
            self::place($id, $declared, $waitsFor, $sorted, []);
        }

        return array_values($sorted);
    }

    /**
     * Places the steps a step waits for, in their order of declaration, then the step.
     *
     * @param array<string, StepDefinition>        $declared
     * @param array<string, array<string, string>> $waitsFor
     * @param array<string, StepDefinition>        $sorted   the steps placed, in their order
     * @param array<string, string>                $placing  the steps being placed, which wait for this one
     */
    private static function place(string $id, array $declared, array $waitsFor, array &$sorted, array $placing): void
    {
        if (isset($sorted[$id])) {
            return;
        }

        if (isset($placing[$id])) {
            throw InvalidFlow::because(\sprintf('The steps "%s" ask to come before one another in a circle.', implode('", "', $placing)));
        }

        $placing[$id] = $id;

        foreach (array_keys($declared) as $other) {
            if (isset($waitsFor[$id][$other])) {
                self::place($other, $declared, $waitsFor, $sorted, $placing);
            }
        }

        $sorted[$id] = $declared[$id];
    }
}
