<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * The rules of SCORM 2004 that tie an element to others: an element of an objective or an interaction needs its
 * identifier first, and a response the type of its interaction; the identifier of an objective is unique among the
 * objectives, or among those of its interaction, and never changes; the completion and the success are evaluated
 * against the completion threshold and the scaled passing score. SCORM 1.2 has none of them.
 */
final class CmiRules
{
    private function __construct()
    {
    }

    /**
     * @return ?array{Mistake, string}
     */
    public static function dependency(CmiData $data, ElementName $name): ?array
    {
        if (ScormVersion::Scorm2004 !== $data->version || [] === $name->indexes) {
            return null;
        }

        $collection = $name->collectionPattern(0);

        if (('cmi.objectives' === $collection || 'cmi.interactions' === $collection) && $collection.'.n.id' !== $name->pattern && null === $data->value($name->sibling(0, 'id'))) {
            return [Mistake::Dependency, 'The identifier is set first.'];
        }

        if (\in_array($name->pattern, ['cmi.interactions.n.correct_responses.n.pattern', 'cmi.interactions.n.learner_response'], true) && null === $data->value($name->sibling(0, 'type'))) {
            return [Mistake::Dependency, 'The type of the interaction is set first.'];
        }

        return null;
    }

    /**
     * @return ?array{Mistake, string}
     */
    public static function uniqueness(CmiData $data, ElementName $name, string $text): ?array
    {
        if (ScormVersion::Scorm2004 !== $data->version) {
            return null;
        }

        if ('cmi.objectives.n.id' === $name->pattern) {
            $current = $data->value($name->name);

            if (null !== $current && $current !== $text) {
                return [Mistake::SetFailed, 'The identifier of an objective never changes.'];
            }

            return self::duplicate($data, 'cmi.objectives', $name->indexes[0], $text);
        }

        if ('cmi.interactions.n.objectives.n.id' === $name->pattern) {
            return self::duplicate($data, $name->collection(1), $name->indexes[1], $text);
        }

        return null;
    }

    /**
     * The value SCORM 2004 evaluates rather than keeps, or null.
     */
    public static function evaluated(CmiData $data, string $pattern): ?string
    {
        if (ScormVersion::Scorm2004 !== $data->version) {
            return null;
        }

        $threshold = $data->value('cmi.completion_threshold');

        if ('cmi.completion_status' === $pattern && null !== $threshold) {
            $progress = $data->value('cmi.progress_measure');

            if (null === $progress) {
                return 'unknown';
            }

            return (float) $progress >= (float) $threshold ? 'completed' : 'incomplete';
        }

        $passing = $data->value('cmi.scaled_passing_score');

        if ('cmi.success_status' === $pattern && null !== $passing) {
            $scaled = $data->value('cmi.score.scaled');

            if (null === $scaled) {
                return 'unknown';
            }

            return (float) $scaled >= (float) $passing ? 'passed' : 'failed';
        }

        return null;
    }

    /**
     * @return ?array{Mistake, string}
     */
    private static function duplicate(CmiData $data, string $collection, int $index, string $text): ?array
    {
        for ($other = 0, $count = $data->count($collection); $other < $count; ++$other) {
            if ($other !== $index && $data->value($collection.'.'.$other.'.id') === $text) {
                return [Mistake::SetFailed, 'The identifier is already given to another element of the collection.'];
            }
        }

        return null;
    }
}
