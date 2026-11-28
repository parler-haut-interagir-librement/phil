<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Override;

/**
 * Where the editors' overrides are kept, by source; the application writes them, the importer reads them at each
 * refresh.
 */
interface OverrideStore
{
    /**
     * @return iterable<EditorOverride> the overrides of the events of a source, one per key
     */
    public function forSource(string $sourceId): iterable;
}
