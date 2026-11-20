<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Tests\Fixture;

use Phil\ElearningPackages\Sequencing\ActivityTree;
use Phil\ElearningPackages\Sequencing\AttemptResult;
use Phil\ElearningPackages\Sequencing\NavigationRequest;
use Phil\ElearningPackages\Sequencing\Sequencer;
use Phil\ElearningPackages\Sequencing\SequencingReader;
use Phil\ElearningPackages\Sequencing\SequencingResult;
use Phil\ElearningPackages\Sequencing\SequencingState;

/**
 * Hand-written SCORM manifests for the sequencing tests, built from small pieces, and a session that walks one.
 */
final class SequencingManifests
{
    public ActivityTree $tree;

    public SequencingState $state;

    private readonly Sequencer $sequencer;

    private function __construct(string $xml)
    {
        $this->tree = new SequencingReader()->read($xml);
        $this->state = SequencingState::begin(42);
        $this->sequencer = new Sequencer();
    }

    /**
     * A session on a SCORM 2004 organization holding these items, with this sequencing of its own.
     */
    public static function session(string $items, string $sequencing = '<imsss:sequencing><imsss:controlMode flow="true"/></imsss:sequencing>', string $collection = ''): self
    {
        return new self(self::scorm2004($items, $sequencing, $collection));
    }

    /**
     * A session on the default organization of a SCORM 1.2 manifest holding these items.
     */
    public static function scorm12Session(string $items): self
    {
        return new self(self::scorm12($items));
    }

    public static function scorm2004(string $items, string $sequencing = '', string $collection = ''): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest identifier="seq" xmlns="http://www.imsglobal.org/xsd/imscp_v1p1" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_v1p3"
                xmlns:imsss="http://www.imsglobal.org/xsd/imsss" xmlns:adlseq="http://www.adlnet.org/xsd/adlseq_v1p3">
              <metadata><schema>ADL SCORM</schema><schemaversion>2004 4th Edition</schemaversion></metadata>
              <organizations default="org">
                <organization identifier="org"><title>Course</title>{$items}{$sequencing}</organization>
              </organizations>
              <resources><resource identifier="r" type="webcontent" adlcp:scormType="sco" href="index.html"/></resources>
              {$collection}
            </manifest>
            XML;
    }

    public static function scorm12(string $items): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest identifier="old" xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2">
              <metadata><schema>ADL SCORM</schema><schemaversion>1.2</schemaversion></metadata>
              <organizations default="org"><organization identifier="org"><title>Course</title>{$items}</organization></organizations>
              <resources><resource identifier="r" type="webcontent" adlcp:scormtype="sco" href="index.html"/></resources>
            </manifest>
            XML;
    }

    /**
     * An item: a leaf launching the resource when it has no children, else a cluster.
     */
    public static function item(string $identifier, string $sequencing = '', string $children = '', string $extra = ''): string
    {
        $reference = '' === $children ? ' identifierref="r"' : '';

        return \sprintf('<item identifier="%s"%s><title>%s</title>%s%s%s</item>', $identifier, $reference, strtoupper($identifier), $children, $extra, $sequencing);
    }

    public static function sequencing(string $inner, string $attributes = ''): string
    {
        // Rules of several timings written one after the other share one <imsss:sequencingRules>.
        return \sprintf('<imsss:sequencing%s>%s</imsss:sequencing>', '' === $attributes ? '' : ' '.$attributes, str_replace('</imsss:sequencingRules><imsss:sequencingRules>', '', $inner));
    }

    public static function flow(string $more = ''): string
    {
        return self::sequencing('<imsss:controlMode flow="true"/>'.$more);
    }

    /**
     * Sequencing rules of one timing, `pre`, `exit` or `post`, each `[action, conditions, combination]`.
     *
     * @param list<array{0: string, 1: string, 2?: string}> $rules
     */
    public static function rules(string $timing, array $rules): string
    {
        $markup = '';

        foreach ($rules as $rule) {
            $markup .= \sprintf('<imsss:%sConditionRule><imsss:ruleConditions conditionCombination="%s">%s</imsss:ruleConditions><imsss:ruleAction action="%s"/></imsss:%sConditionRule>', $timing, $rule[2] ?? 'all', $rule[1], $rule[0], $timing);
        }

        return '<imsss:sequencingRules>'.$markup.'</imsss:sequencingRules>';
    }

    public static function condition(string $condition, bool $not = false, string $attributes = ''): string
    {
        return \sprintf('<imsss:ruleCondition condition="%s"%s%s/>', $condition, $not ? ' operator="not"' : '', '' === $attributes ? '' : ' '.$attributes);
    }

    /**
     * A rollup rule: its action, its conditions, the child activity set and its attributes.
     */
    public static function rollupRule(string $action, string $conditions, string $set = 'all', string $attributes = '', string $combination = 'any'): string
    {
        return \sprintf('<imsss:rollupRule childActivitySet="%s"%s><imsss:rollupConditions conditionCombination="%s">%s</imsss:rollupConditions><imsss:rollupAction action="%s"/></imsss:rollupRule>', $set, '' === $attributes ? '' : ' '.$attributes, $combination, $conditions, $action);
    }

    public static function rollupCondition(string $condition, bool $not = false): string
    {
        return \sprintf('<imsss:rollupCondition condition="%s"%s/>', $condition, $not ? ' operator="not"' : '');
    }

    public function navigate(NavigationRequest $request, ?string $target = null): SequencingResult
    {
        $result = $this->sequencer->navigate($this->tree, $this->state, $request, $target);
        $this->state = $result->state;

        return $result;
    }

    /**
     * Sends the request and returns what it delivered, or the outcome when nothing was.
     */
    public function go(NavigationRequest $request, ?string $target = null): string
    {
        $result = $this->navigate($request, $target);

        return $result->activity ?? $result->outcome->value;
    }

    public function report(AttemptResult $result): void
    {
        $this->state = $this->sequencer->report($this->tree, $this->state, $result);
    }

    public function lessonStatus(string $activity): string
    {
        return $this->sequencer->lessonStatus($this->tree, $this->state, $activity);
    }
}
