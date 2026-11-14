<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\AudienceProvider;

/**
 * The audiences `<prefix>:<n>`, each made of the recipients 1 to n.
 */
final readonly class Audience implements AudienceProvider
{
    public function __construct(
        private string $prefix = 'segment',
    ) {
    }

    #[Override]
    public function supports(string $audience): bool
    {
        return str_starts_with($audience, $this->prefix.':');
    }

    #[Override]
    public function recipients(string $audience): iterable
    {
        $count = (int) substr($audience, \strlen($this->prefix) + 1);

        for ($i = 1; $i <= $count; ++$i) {
            yield (string) $i;
        }
    }
}
