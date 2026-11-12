<?php

declare(strict_types=1);

namespace Phil\CreationDisclosure;

/**
 * What a work, or one of its parts, owes to generative AI, read from its digital source types: the view a reader
 * filters on. `None` is the AI-free view; `Undeclared` stays visible and never counts as AI-free.
 */
enum GenerativeAi: string
{
    /** Nothing in it was generated: captured, digitised, made or edited by people, or computed by plain algorithms. */
    case None = 'none';

    /** Nothing says how it was made. */
    case Undeclared = 'undeclared';

    /** It may hold generated elements: altered by algorithms or composed of elements of unstated origin. */
    case Possible = 'possible';

    /** Some of it was generated: edited with generative AI, or composed with generated elements. */
    case Partial = 'partial';

    /** All of it was created with generative AI. */
    case Full = 'full';

    /**
     * The view of a whole work from that of its parts: `Full` only when every part is, `Partial` as soon as one part
     * holds generated content, then `Possible`, then `Undeclared`; `None` only when every part is AI-free. A work
     * without parts is undeclared.
     */
    public static function combine(self ...$parts): self
    {
        if ([] === $parts) {
            return self::Undeclared;
        }

        $present = array_fill_keys(array_map(static fn (self $part): string => $part->value, $parts), true);

        if ([self::Full->value => true] === $present) {
            return self::Full;
        }

        if (isset($present[self::Full->value]) || isset($present[self::Partial->value])) {
            return self::Partial;
        }

        foreach ([self::Possible, self::Undeclared] as $view) {
            if (isset($present[$view->value])) {
                return $view;
            }
        }

        return self::None;
    }

    /**
     * Whether this view is at most another one, in the order `None`, `Undeclared`, `Possible`, `Partial`, `Full`.
     */
    public function isAtMost(self $limit): bool
    {
        return $this->rank() <= $limit->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::None => 0,
            self::Undeclared => 1,
            self::Possible => 2,
            self::Partial => 3,
            self::Full => 4,
        };
    }
}
