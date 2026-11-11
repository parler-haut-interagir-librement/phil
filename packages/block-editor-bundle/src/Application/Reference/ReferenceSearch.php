<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Reference;

/**
 * Finds what a writer may mention with `@`, or link to with `[[`, from the few letters typed after the trigger. The
 * application writes one per menu — people, pages, articles — and names it in the configuration; without one, the
 * trigger opens nothing.
 */
interface ReferenceSearch
{
    /**
     * @param string $query what the writer typed after the trigger, trimmed, possibly empty
     * @param int    $limit the most suggestions the menu shows
     *
     * @return iterable<Suggestion> the best first
     */
    public function search(string $query, int $limit): iterable;
}
