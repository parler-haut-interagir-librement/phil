<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Dom\Element;
use Override;
use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Contract\UjoRule;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;

/**
 * `duplicate-id`: an identifier names one element. A label, a description of ARIA or a link to a fragment reaches
 * only the first element of an identifier given twice: the duplicate is an error when something refers to the
 * identifier, a warning otherwise. It is reported once, where the identifier is given the second time.
 *
 * In UJO, the identifiers are those of the `anchor` tunes, of the `footnote` blocks and of the elements of the HTML
 * fields, across the whole document; the references, those of the HTML fields, footnote references included.
 */
final class DuplicateId implements HtmlRule, UjoRule
{
    private const array REFERRING = ['aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-owns', 'aria-details', 'aria-flowto', 'aria-errormessage', 'for', 'headers', 'list', 'form'];

    #[Override]
    public function id(): string
    {
        return 'duplicate-id';
    }

    #[Override]
    public function criterion(): SuccessCriterion
    {
        return SuccessCriterion::NameRoleValue;
    }

    #[Override]
    public function severity(): Severity
    {
        return Severity::Warning;
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkHtml(HtmlTarget $target): iterable
    {
        $places = [];

        foreach ($target->identifiers() as $id => $elements) {
            $places[$id] = array_map(static fn (Element $element): array => [$target, $element], $elements);
        }

        return $this->check($places, [$target]);
    }

    /**
     * @return iterable<Violation>
     */
    #[Override]
    public function checkUjo(UjoTarget $target): iterable
    {
        $places = [];
        $fields = [];

        foreach ($target->blocks() as $visit) {
            $anchor = $visit->block->tune('anchor');
            $anchor = \is_array($anchor) ? $anchor['anchor'] ?? null : null;
            $footnote = 'footnote' === $visit->block->type ? UjoTarget::field($visit->block, 'id') : null;

            foreach ([$anchor, $footnote] as $id) {
                if (\is_string($id) && '' !== $id) {
                    $places[$id][] = $visit->locate();
                }
            }

            foreach ($target->fragments($visit) as $fragment) {
                $fields[] = $fragment->html;

                foreach ($fragment->html->identifiers() as $id => $elements) {
                    foreach ($elements as $element) {
                        $places[$id][] = [$fragment->html, $element];
                    }
                }
            }
        }

        return $this->check($places, $fields);
    }

    /**
     * @param array<array-key, list<Location|array{HtmlTarget, Element}>> $places where each identifier is given: a
     *                                                                            location, or an element to locate
     * @param list<HtmlTarget>                                            $fields the HTML that may refer to them
     *
     * @return list<Violation>
     */
    private function check(array $places, array $fields): array
    {
        $violations = [];
        $duplicates = array_filter($places, static fn (array $found): bool => \count($found) > 1);
        $references = [] === $duplicates ? [] : $this->references($fields);

        foreach ($duplicates as $id => $found) {
            $second = $found[1];

            $violations[] = new Violation(
                $second instanceof Location ? $second : $second[0]->locate($second[1]),
                new Message('duplicate-id.duplicate', ['id' => $id, 'count' => \count($found)]),
                ($references[$id] ?? false) ? Severity::Error : null,
            );
        }

        return $violations;
    }

    /**
     * The identifiers something in the HTML refers to: the attributes of ARIA and of forms that name identifiers, and
     * the links to a fragment, footnote references included. Computed only when an identifier is given twice.
     *
     * @param list<HtmlTarget> $fields
     *
     * @return array<string, true>
     */
    private function references(array $fields): array
    {
        $references = [];

        foreach ($fields as $field) {
            foreach ($field->elements() as $element) {
                foreach ($element->attributes as $attribute) {
                    $value = $attribute->value;
                    $ids = match (true) {
                        \in_array($attribute->name, self::REFERRING, true) => explode(' ', HtmlTarget::collapse($value)),
                        'href' === $attribute->name && str_starts_with($value, '#') => [rawurldecode(substr($value, 1))],
                        'data-footnote' === $attribute->name => [$value],
                        default => [],
                    };

                    foreach ($ids as $id) {
                        $references[$id] = true;
                    }
                }
            }
        }

        return $references;
    }
}
