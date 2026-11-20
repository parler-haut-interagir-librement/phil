<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

use DOMElement;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\Runtime\Duration;
use Phil\ElearningPackages\Sequencing\Prerequisite\Prerequisites;
use Phil\ElearningPackages\Warnings;
use Phil\ElearningPackages\Xml\XmlDocument;

/**
 * Builds the activities of one organization.
 *
 * @internal used by `SequencingReader`
 */
final readonly class SequencingBuilder
{
    /**
     * @param array<string, DOMElement> $collection
     */
    public function __construct(
        private PackageFormat $format,
        private array $collection,
        private Warnings $warnings,
    ) {
    }

    public function activity(DOMElement $element, bool $isItem): Activity
    {
        $identifier = (string) XmlDocument::attribute($element, 'identifier');
        $children = array_map(fn (DOMElement $child): Activity => $this->activity($child, true), XmlDocument::children($element, 'item'));
        $reference = XmlDocument::attribute($element, 'identifierref');

        return new Activity(
            $identifier,
            XmlDocument::childText($element, 'title') ?? $identifier,
            PackageFormat::Scorm12 === $this->format ? new Sequencing(new ControlMode(flow: true), deliveryControls: new DeliveryControls(true, true, true)) : $this->sequencing($element, $identifier),
            $children,
            'false' !== strtolower(XmlDocument::attribute($element, 'isvisible') ?? 'true'),
            $isItem && null !== $reference && '' !== $reference ? $reference : null,
            PackageFormat::Scorm12 === $this->format ? $this->prerequisites($element, $identifier) : null,
        );
    }

    private function prerequisites(DOMElement $element, string $identifier): ?Prerequisites
    {
        $prerequisites = XmlDocument::child($element, 'prerequisites');

        if (!$prerequisites instanceof DOMElement || '' === XmlDocument::text($prerequisites)) {
            return null;
        }

        $type = XmlDocument::attribute($prerequisites, 'type');

        if (null !== $type && 'aicc_script' !== strtolower($type)) {
            $this->warnings->add(\sprintf('The prerequisites of the item "%s" are of the type "%s", which the reader does not know: they are left out.', $identifier, mb_substr($type, 0, 40)));

            return null;
        }

        try {
            return Prerequisites::parse(XmlDocument::text($prerequisites));
        } catch (PackageRefused $packageRefused) {
            $this->warnings->add(\sprintf('The item "%s": %s They are left out.', $identifier, $packageRefused->getMessage()));

            return null;
        }
    }

    private function sequencing(DOMElement $element, string $identifier): Sequencing
    {
        $own = XmlDocument::child($element, 'sequencing');

        if (!$own instanceof DOMElement) {
            return new Sequencing();
        }

        $reference = XmlDocument::attribute($own, 'IDRef');
        $shared = null === $reference ? null : ($this->collection[$reference] ?? null);

        if (null !== $reference && null === $shared) {
            $this->warnings->add(\sprintf('The sequencing of "%s" names the entry "%s", which the sequencing collection does not hold.', $identifier, mb_substr($reference, 0, 120)));
        }

        $part = static fn (string $localName): ?DOMElement => XmlDocument::child($own, $localName) ?? (null === $shared ? null : XmlDocument::child($shared, $localName));
        $rules = $part('sequencingRules');
        $rollup = $part('rollupRules');
        $considerations = $part('rollupConsiderations');

        return new Sequencing(
            $this->controlMode($part('controlMode'), $identifier),
            $rules instanceof DOMElement ? $this->rules($rules, RuleTiming::Pre, $identifier) : [],
            $rules instanceof DOMElement ? $this->rules($rules, RuleTiming::Exit, $identifier) : [],
            $rules instanceof DOMElement ? $this->rules($rules, RuleTiming::Post, $identifier) : [],
            $this->limitConditions($part('limitConditions'), $identifier),
            $rollup instanceof DOMElement ? $this->rollupRules($rollup, $identifier) : [],
            $this->rollupControls($rollup, $considerations, $identifier),
            $this->objectives($part('objectives'), $identifier),
            $this->randomization($part('randomizationControls'), $identifier),
            $this->delivery($part('deliveryControls'), $identifier),
        );
    }

    private function controlMode(?DOMElement $element, string $identifier): ControlMode
    {
        if (!$element instanceof DOMElement) {
            return new ControlMode();
        }

        return new ControlMode(
            $this->boolean($element, 'choice', true, $identifier),
            $this->boolean($element, 'choiceExit', true, $identifier),
            $this->boolean($element, 'flow', false, $identifier),
            $this->boolean($element, 'forwardOnly', false, $identifier),
            $this->boolean($element, 'useCurrentAttemptObjectiveInfo', true, $identifier),
            $this->boolean($element, 'useCurrentAttemptProgressInfo', true, $identifier),
        );
    }

    /**
     * @return list<SequencingRule>
     */
    private function rules(DOMElement $element, RuleTiming $timing, string $identifier): array
    {
        $elements = XmlDocument::children($element, $timing->value);

        if (\count($elements) > SequencingReader::MAX_RULES) {
            throw PackageRefused::because(\sprintf('The activity "%s" declares more than %d rules of one kind.', $identifier, SequencingReader::MAX_RULES));
        }

        $rules = [];

        foreach ($elements as $rule) {
            $action = RuleAction::tryFrom(XmlDocument::attribute(XmlDocument::child($rule, 'ruleAction') ?? $rule, 'action') ?? '');

            if (null === $action || $action->timing() !== $timing) {
                $this->warnings->add(\sprintf('A %s of the activity "%s" has no action that rule may take: it is left out.', $timing->value, $identifier));

                continue;
            }

            $conditions = XmlDocument::child($rule, 'ruleConditions');

            if (!$conditions instanceof DOMElement) {
                $this->warnings->add(\sprintf('A rule of the activity "%s" has no condition: it is left out.', $identifier));

                continue;
            }

            $list = $this->conditions($conditions, 'ruleCondition', false, $identifier);

            if (null === $list) {
                continue;
            }

            $rules[] = new SequencingRule($list, $action, 'any' !== XmlDocument::attribute($conditions, 'conditionCombination'));
        }

        return $rules;
    }

    /**
     * @return ?non-empty-list<RuleCondition> null when a condition cannot be used, which is noted
     */
    private function conditions(DOMElement $element, string $localName, bool $rollup, string $identifier): ?array
    {
        $elements = XmlDocument::children($element, $localName);

        if (\count($elements) > SequencingReader::MAX_CONDITIONS) {
            throw PackageRefused::because(\sprintf('A rule of the activity "%s" holds more than %d conditions.', $identifier, SequencingReader::MAX_CONDITIONS));
        }

        $conditions = [];

        foreach ($elements as $condition) {
            $type = ConditionType::tryFrom(XmlDocument::attribute($condition, 'condition') ?? '');

            if (null === $type || ($rollup && !$type->isRollupCondition())) {
                $this->warnings->add(\sprintf('A rule of the activity "%s" tests a condition it may not: it is left out.', $identifier));

                return null;
            }

            $objective = XmlDocument::attribute($condition, 'referencedObjective');
            $conditions[] = new RuleCondition(
                $type,
                'not' === XmlDocument::attribute($condition, 'operator'),
                null === $objective || '' === $objective ? null : $objective,
                $this->decimal(XmlDocument::attribute($condition, 'measureThreshold'), -1.0, 1.0, 0.0, $identifier),
            );
        }

        if ([] === $conditions) {
            $this->warnings->add(\sprintf('A rule of the activity "%s" has no condition: it is left out.', $identifier));

            return null;
        }

        return $conditions;
    }

    private function limitConditions(?DOMElement $element, string $identifier): LimitConditions
    {
        if (!$element instanceof DOMElement) {
            return new LimitConditions();
        }

        $limit = XmlDocument::attribute($element, 'attemptLimit');
        $duration = XmlDocument::attribute($element, 'attemptAbsoluteDurationLimit');
        $attempts = null;

        if (null !== $limit) {
            if (1 === preg_match('/^\d{1,9}$/D', $limit)) {
                $attempts = (int) $limit;
            } else {
                $this->warnings->add(\sprintf('The attempt limit of the activity "%s" is not a whole number: no limit applies.', $identifier));
            }
        }

        $span = null === $duration ? null : Duration::parseInterval($duration);

        if (null !== $duration && !$span instanceof Duration) {
            $this->warnings->add(\sprintf('The duration limit of the activity "%s" is not a duration: no limit applies.', $identifier));
        }

        return new LimitConditions(0 === $attempts ? null : $attempts, $span);
    }

    /**
     * @return list<RollupRule>
     */
    private function rollupRules(DOMElement $element, string $identifier): array
    {
        $elements = XmlDocument::children($element, 'rollupRule');

        if (\count($elements) > SequencingReader::MAX_RULES) {
            throw PackageRefused::because(\sprintf('The activity "%s" declares more than %d rollup rules.', $identifier, SequencingReader::MAX_RULES));
        }

        $rules = [];

        foreach ($elements as $rule) {
            $action = RollupAction::tryFrom(XmlDocument::attribute(XmlDocument::child($rule, 'rollupAction') ?? $rule, 'action') ?? '');
            $set = ChildActivitySet::tryFrom(XmlDocument::attribute($rule, 'childActivitySet') ?? ChildActivitySet::All->value);

            if (null === $action || null === $set) {
                $this->warnings->add(\sprintf('A rollup rule of the activity "%s" has no action or no child activity set it may take: it is left out.', $identifier));

                continue;
            }

            $conditions = XmlDocument::child($rule, 'rollupConditions');

            if (!$conditions instanceof DOMElement) {
                $this->warnings->add(\sprintf('A rule of the activity "%s" has no condition: it is left out.', $identifier));

                continue;
            }

            $list = $this->conditions($conditions, 'rollupCondition', true, $identifier);

            if (null === $list) {
                continue;
            }

            $count = XmlDocument::attribute($rule, 'minimumCount') ?? '0';
            $rules[] = new RollupRule(
                $list,
                $action,
                $set,
                1 === preg_match('/^\d{1,9}$/D', $count) ? (int) $count : 0,
                $this->decimal(XmlDocument::attribute($rule, 'minimumPercent'), 0.0, 1.0, 0.0, $identifier),
                'all' === XmlDocument::attribute($conditions, 'conditionCombination'),
            );
        }

        return $rules;
    }

    private function rollupControls(?DOMElement $rollup, ?DOMElement $considerations, string $identifier): RollupControls
    {
        $requirement = function (string $name) use ($considerations, $identifier): Requirement {
            $value = $considerations instanceof DOMElement ? XmlDocument::attribute($considerations, $name) : null;
            $requirement = Requirement::tryFrom($value ?? Requirement::Always->value);

            if (null === $requirement) {
                $this->warnings->add(\sprintf('The %s of the activity "%s" is not one SCORM defines: always applies.', $name, $identifier));
            }

            return $requirement ?? Requirement::Always;
        };

        return new RollupControls(
            !$rollup instanceof DOMElement || $this->boolean($rollup, 'rollupObjectiveSatisfied', true, $identifier),
            !$rollup instanceof DOMElement || $this->boolean($rollup, 'rollupProgressCompletion', true, $identifier),
            $rollup instanceof DOMElement ? $this->decimal(XmlDocument::attribute($rollup, 'objectiveMeasureWeight'), 0.0, 1.0, 1.0, $identifier) : 1.0,
            $requirement('requiredForSatisfied'),
            $requirement('requiredForNotSatisfied'),
            $requirement('requiredForCompleted'),
            $requirement('requiredForIncomplete'),
            !$considerations instanceof DOMElement || $this->boolean($considerations, 'measureSatisfactionIfActive', true, $identifier),
        );
    }

    /**
     * @return list<Objective>
     */
    private function objectives(?DOMElement $element, string $identifier): array
    {
        $primary = $element instanceof DOMElement ? XmlDocument::child($element, 'primaryObjective') : null;
        $others = $element instanceof DOMElement ? XmlDocument::children($element, 'objective') : [];

        if (\count($others) + 1 > SequencingReader::MAX_OBJECTIVES) {
            throw PackageRefused::because(\sprintf('The activity "%s" declares more than %d objectives.', $identifier, SequencingReader::MAX_OBJECTIVES));
        }

        $objectives = [$primary instanceof DOMElement ? $this->objective($primary, true, $identifier) : new Objective(primary: true)];
        $seen = [];

        if (null !== $objectives[0]->id) {
            $seen[$objectives[0]->id] = true;
        }

        foreach ($others as $other) {
            $objective = $this->objective($other, false, $identifier);

            if (null === $objective->id || isset($seen[$objective->id])) {
                $this->warnings->add(\sprintf('An objective of the activity "%s" has no identifier, or one used before: it is left out.', $identifier));

                continue;
            }

            $seen[$objective->id] = true;
            $objectives[] = $objective;
        }

        return $objectives;
    }

    private function objective(DOMElement $element, bool $primary, string $identifier): Objective
    {
        $maps = XmlDocument::children($element, 'mapInfo');

        if (\count($maps) > SequencingReader::MAX_MAPS) {
            throw PackageRefused::because(\sprintf('An objective of the activity "%s" holds more than %d maps.', $identifier, SequencingReader::MAX_MAPS));
        }

        $id = XmlDocument::attribute($element, 'objectiveID');
        $list = [];

        foreach ($maps as $map) {
            $target = XmlDocument::attribute($map, 'targetObjectiveID');

            if (null === $target || '' === $target) {
                $this->warnings->add(\sprintf('A map of an objective of the activity "%s" names no global objective: it is left out.', $identifier));

                continue;
            }

            $list[] = new ObjectiveMap(
                $target,
                $this->boolean($map, 'readSatisfiedStatus', true, $identifier),
                $this->boolean($map, 'readNormalizedMeasure', true, $identifier),
                $this->boolean($map, 'writeSatisfiedStatus', false, $identifier),
                $this->boolean($map, 'writeNormalizedMeasure', false, $identifier),
            );
        }

        return new Objective(
            null === $id || '' === $id ? null : $id,
            $primary,
            $this->boolean($element, 'satisfiedByMeasure', false, $identifier),
            $this->decimal(XmlDocument::childText($element, 'minNormalizedMeasure'), -1.0, 1.0, 1.0, $identifier),
            $list,
        );
    }

    private function randomization(?DOMElement $element, string $identifier): RandomizationControls
    {
        if (!$element instanceof DOMElement) {
            return new RandomizationControls();
        }

        $count = XmlDocument::attribute($element, 'selectCount');

        if (null !== $count && 1 !== preg_match('/^\d{1,9}$/D', $count)) {
            $this->warnings->add(\sprintf('The select count of the activity "%s" is not a whole number: every child is offered.', $identifier));
            $count = null;
        }

        return new RandomizationControls(
            $this->timing($element, 'selectionTiming', $identifier),
            null === $count ? null : (int) $count,
            $this->timing($element, 'randomizationTiming', $identifier),
            $this->boolean($element, 'reorderChildren', false, $identifier),
        );
    }

    private function timing(DOMElement $element, string $name, string $identifier): RandomizationTiming
    {
        $timing = RandomizationTiming::tryFrom(XmlDocument::attribute($element, $name) ?? RandomizationTiming::Never->value);

        if (null === $timing) {
            $this->warnings->add(\sprintf('The %s of the activity "%s" is not one SCORM defines: never applies.', $name, $identifier));
        }

        return $timing ?? RandomizationTiming::Never;
    }

    private function delivery(?DOMElement $element, string $identifier): DeliveryControls
    {
        if (!$element instanceof DOMElement) {
            return new DeliveryControls();
        }

        return new DeliveryControls(
            $this->boolean($element, 'tracked', true, $identifier),
            $this->boolean($element, 'completionSetByContent', false, $identifier),
            $this->boolean($element, 'objectiveSetByContent', false, $identifier),
        );
    }

    private function boolean(DOMElement $element, string $name, bool $default, string $identifier): bool
    {
        $value = XmlDocument::attribute($element, $name);

        return match ($value) {
            null => $default,
            'true', '1' => true,
            'false', '0' => false,
            default => $this->fallback(\sprintf('The %s of the activity "%s" is not true or false: %s applies.', $name, $identifier, $default ? 'true' : 'false'), $default),
        };
    }

    private function decimal(?string $value, float $min, float $max, float $default, string $identifier): float
    {
        if (null === $value) {
            return $default;
        }

        $number = is_numeric($value) ? (float) $value : null;

        if (null === $number || $number < $min || $number > $max) {
            return $this->fallback(\sprintf('A number of the activity "%s" is not a decimal from %s to %s: %s applies.', $identifier, $min, $max, $default), $default);
        }

        return $number;
    }

    /**
     * @template T
     *
     * @param T $default
     *
     * @return T
     */
    private function fallback(string $note, mixed $default): mixed
    {
        $this->warnings->add($note);

        return $default;
    }
}
