<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Contract;

use Phil\Ujo\Document;

/**
 * What the application gives `PublicationGuard` to check of the subject of a workflow: the HTML or the UJO document a
 * publication would put before readers.
 */
interface SubjectContent
{
    /**
     * The content of the subject: HTML in a string, a UJO document, or `null` when the subject has nothing to check.
     */
    public function contentOf(object $subject): string|Document|null;
}
