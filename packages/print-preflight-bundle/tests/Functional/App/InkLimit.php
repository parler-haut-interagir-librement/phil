<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Functional\App;

use Override;
use Phil\PrintPreflight\Check\Check;
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflight\Severity;
use Phil\PrintPreflightBundle\Application\AsPreflightCheck;

/**
 * A check of the application, registered by its attribute: one notice per page.
 */
#[AsPreflightCheck]
final readonly class InkLimit implements Check
{
    #[Override]
    public function check(Document $document, PrintProfile $profile): array
    {
        return array_map(static fn (int $index): Finding => new Finding(Severity::Notice, 'house.ink-limit', 'Checked.', $index + 1), array_keys($document->pages));
    }
}
