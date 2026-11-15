<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use Override;
use Phil\TermsBundle\Application\Contract\TermsNotifier;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

/**
 * The notices, recorded.
 */
final class RecordingNotifier implements TermsNotifier
{
    /** @var list<string> */
    public array $published = [];

    /** @var list<array{string, int, list<string>}> */
    public array $reminders = [];

    #[Override]
    public function published(PolicyVersion $version): void
    {
        $this->published[] = $version->policy.' '.$version->number;
    }

    #[Override]
    public function remind(string $policy, int $version, array $members): void
    {
        $this->reminders[] = [$policy, $version, $members];
    }
}
