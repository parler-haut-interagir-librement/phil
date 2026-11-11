<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Check;

use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PrintProfile;

/**
 * One family of rules the preflight applies. An application adds its own by implementing it.
 */
interface Check
{
    /**
     * The findings of this check, at their own severity: the preflight applies the profile's severities afterwards.
     *
     * @return list<Finding>
     */
    public function check(Document $document, PrintProfile $profile): array;
}
