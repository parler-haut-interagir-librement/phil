<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional\App;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * A Twig extension the application gives the sandbox: its filter is allowed once listed in the configuration.
 */
final class Shout extends AbstractExtension
{
    #[Override]
    public function getFilters(): array
    {
        return [new TwigFilter('shout', static fn (string $text): string => mb_strtoupper($text).'!')];
    }
}
