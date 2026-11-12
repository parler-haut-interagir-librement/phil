<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Rule;

use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Ujo\UjoTarget;

/**
 * A heading of a content, its level from 1 to 6, its text and where it is: an `h1`–`h6` element, an element of role
 * `heading` with its `aria-level`, or a `header` block of UJO.
 */
final readonly class Heading
{
    private const array LEVELS = ['h1' => 1, 'h2' => 2, 'h3' => 3, 'h4' => 4, 'h5' => 5, 'h6' => 6];

    public function __construct(
        public int $level,
        public string $text,
        public Location $location,
    ) {
    }

    /**
     * The headings of an HTML content that are shown, in document order.
     *
     * @return list<self>
     */
    public static function ofHtml(HtmlTarget $target): array
    {
        $headings = [];

        foreach ($target->elements() as $element) {
            $tag = self::LEVELS[$element->localName] ?? null;

            if ($target->isHidden($element) || (null === $tag && !$target->hasRole($element, 'heading'))) {
                continue;
            }

            $level = self::level($element->getAttribute('aria-level'), $tag ?? 2);
            $headings[] = new self($level, $target->accessibleName($element), $target->locate($element));
        }

        return $headings;
    }

    /**
     * The headings of a UJO document, in document order: its `header` blocks and the headings its HTML fields hold.
     *
     * @return list<self>
     */
    public static function ofUjo(UjoTarget $target): array
    {
        $headings = [];

        foreach ($target->blocks() as $visit) {
            if ('header' === $visit->block->type) {
                $level = UjoTarget::field($visit->block, 'level');
                $headings[] = new self(
                    self::level(\is_int($level) || \is_string($level) ? (string) $level : null, 2),
                    UjoTarget::text(UjoTarget::field($visit->block, 'text')),
                    $visit->locate('text'),
                );
            }

            foreach ($target->fragments($visit) as $fragment) {
                array_push($headings, ...self::ofHtml($fragment->html));
            }
        }

        return $headings;
    }

    /**
     * A level from 1 to 6 read from a text, else `$default`.
     */
    private static function level(?string $value, int $default): int
    {
        return null !== $value && 1 === preg_match('/^\s*[1-6]\s*$/', $value) ? (int) $value : $default;
    }
}
